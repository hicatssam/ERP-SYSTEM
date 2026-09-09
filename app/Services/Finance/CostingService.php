<?php

namespace App\Services\Finance;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderInventoryConsumption;
use App\Models\StockMovement;
use App\Services\ModuleService;
use App\Services\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CostingService
{
    public function __construct(
        private ModuleService $modules,
    ) {}

    public function enabled(): bool
    {
        return $this->modules->isEnabled('costing');
    }

    /**
     * Snapshot the actual weighted-average inventory cost at confirmation time.
     * Missing/zero costs never block a sale; the line remains unsnapshotted and
     * is surfaced by the profitability data-quality metrics.
     */
    public function snapshotAtConfirmation(Order $order, ?User $user = null): array
    {
        if (! $this->enabled()) {
            return ['snapshotted' => 0, 'missing_costs' => []];
        }

        $order->loadMissing(['items.product']);
        $allocations = $this->netRevenueAllocations($order);
        $done = 0;
        $missing = [];

        $consumptions = OrderInventoryConsumption::query()
            ->where('order_id', $order->id)
            ->whereNull('restored_at')
            ->get()
            ->groupBy('order_item_id');

        foreach ($order->items as $item) {
            if ($item->cost_snapshotted_at) {
                continue;
            }

            $quantity = (float) $item->quantity;
            $netRevenue = (float) ($allocations[$item->id] ?? $item->line_total);
            $itemConsumptions = $consumptions->get($item->id, collect());

            if ($itemConsumptions->isNotEmpty()) {
                $hasMissingCost = $itemConsumptions->contains(
                    fn ($row): bool =>
                        (float) $row->consumed_quantity > 0
                        && (float) ($row->unit_cost_snapshot ?? 0) <= 0
                );

                $costTotal = round(
                    (float) $itemConsumptions->sum(
                        fn ($row): float => (float) ($row->total_cost_snapshot ?? 0)
                    ),
                    4
                );

                if ($hasMissingCost || $quantity <= 0) {
                    $item->update([
                        'net_revenue_snapshot' => round($netRevenue, 2),
                        'cost_source' => 'missing_consumption_cost',
                    ]);

                    $missing[] = $item->product_name ?: ('#' . $item->product_id);
                    continue;
                }

                $unitCost = $costTotal / $quantity;

                $item->update([
                    'unit_cost_snapshot' => round($unitCost, 4),
                    'cost_total_snapshot' => round($costTotal, 2),
                    'net_revenue_snapshot' => round($netRevenue, 2),
                    'gross_profit_snapshot' => round($netRevenue - $costTotal, 2),
                    'cost_source' => 'inventory_consumption',
                    'cost_snapshotted_at' => now(),
                ]);

                $done++;
                continue;
            }

            /*
             * Legacy fallback for historical flows that do not have frozen
             * order_inventory_consumptions rows.
             */
            $inventory = Inventory::query()
                ->where('location_id', $order->location_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            $unitCost = (float) ($inventory?->unit_cost ?? 0);

            if ($unitCost <= 0) {
                $item->update([
                    'net_revenue_snapshot' => round($netRevenue, 2),
                    'cost_source' => 'missing_inventory_cost',
                ]);

                $missing[] = $item->product_name ?: ('#' . $item->product_id);
                continue;
            }

            $costTotal = round($unitCost * $quantity, 2);

            $item->update([
                'unit_cost_snapshot' => round($unitCost, 4),
                'cost_total_snapshot' => $costTotal,
                'net_revenue_snapshot' => round($netRevenue, 2),
                'gross_profit_snapshot' => round($netRevenue - $costTotal, 2),
                'cost_source' => 'inventory_average',
                'cost_snapshotted_at' => now(),
            ]);

            $done++;
        }

        if ($user && ($done > 0 || $missing !== [])) {
            ActivityLogger::log(
                userId: $user->id,
                action: 'costing.order_snapshot',
                module: 'costing',
                recordType: 'orders',
                recordId: $order->id,
                oldValues: null,
                newValues: ['snapshotted' => $done],
                metadata: [
                    'location_id' => $order->location_id,
                    'missing_cost_count' => count($missing),
                ],
            );
        }

        return ['snapshotted' => $done, 'missing_costs' => $missing];
    }

    /**
     * Confirmed orders can currently have quantity edits in the ERP.
     * Never replace their historical unit cost with today's cost; keep the
     * original snapshot and only recompute quantity total + revenue allocation.
     */
    public function recalculateConfirmedOrder(Order $order): void
    {
        if (! $this->enabled()) {
            return;
        }

        $order->loadMissing('items');
        $allocations = $this->netRevenueAllocations($order);

        foreach ($order->items as $item) {
            $revenue = round((float) ($allocations[$item->id] ?? $item->line_total), 2);

            // Revenue allocation must stay current even for a line whose cost
            // is still missing.  Never invent a cost just to complete the row.
            $updates = [
                'net_revenue_snapshot' => $revenue,
            ];

            if ($item->cost_snapshotted_at && $item->unit_cost_snapshot !== null) {
                $unit = (float) $item->unit_cost_snapshot;
                $cost = round($unit * (float) $item->quantity, 2);

                $updates['cost_total_snapshot'] = $cost;
                $updates['gross_profit_snapshot'] = round($revenue - $cost, 2);
            }

            $item->update($updates);
        }
    }

    /**
     * Historical backfill uses the stock decrease movement generated by the sale.
     * It never overwrites an existing snapshot unless $force=true.
     */
    public function backfillFromStockMovements(Order $order, bool $force = false): array
    {
        if (! $this->enabled()) {
            return ['snapshotted' => 0, 'missing_costs' => []];
        }

        $order->loadMissing('items');
        $allocations = $this->netRevenueAllocations($order);
        $done = 0;
        $missing = [];

        foreach ($order->items as $item) {
            if ($item->cost_snapshotted_at && ! $force) {
                continue;
            }

            $movements = StockMovement::query()
                ->where('location_id', $order->location_id)
                ->where('product_id', $item->product_id)
                ->where('reference_type', 'orders')
                ->where('reference_id', $order->id)
                ->whereIn('reason', ['order_sale', 'order'])
                ->orderBy('id')
                ->get();

            $unitCost = $this->movementWeightedUnitCost($movements);

            if ($unitCost <= 0) {
                $missing[] = $item->product_name ?: ('#' . $item->product_id);
                continue;
            }

            $quantity = (float) $item->quantity;
            $netRevenue = (float) ($allocations[$item->id] ?? $item->line_total);
            $costTotal = round($unitCost * $quantity, 2);

            $item->update([
                'unit_cost_snapshot' => round($unitCost, 4),
                'cost_total_snapshot' => $costTotal,
                'net_revenue_snapshot' => round($netRevenue, 2),
                'gross_profit_snapshot' => round($netRevenue - $costTotal, 2),
                'cost_source' => 'stock_movement_backfill',
                'cost_snapshotted_at' => $movements->max('created_at') ?? now(),
            ]);

            $done++;
        }

        return ['snapshotted' => $done, 'missing_costs' => $missing];
    }

    private function movementWeightedUnitCost($movements): float
    {
        if ($movements->isEmpty()) {
            return 0.0;
        }

        $qty = 0.0;
        $cost = 0.0;

        foreach ($movements as $movement) {
            $movementQty = abs((float) ($movement->quantity ?? 0));
            $unit = (float) ($movement->base_unit_cost ?? $movement->unit_cost ?? 0);

            if ($movementQty <= 0 || $unit <= 0) {
                continue;
            }

            $qty += $movementQty;
            $cost += $movementQty * $unit;
        }

        return $qty > 0 ? $cost / $qty : 0.0;
    }

    /** @return array<int,float> keyed by order_item id */
    private function netRevenueAllocations(Order $order): array
    {
        $subtotal = (float) $order->items->sum(fn (OrderItem $item) => (float) $item->line_total);
        $discount = max(0, (float) ($order->discount_amount ?? 0));

        if ($subtotal <= 0 || $discount <= 0) {
            return $order->items->mapWithKeys(
                fn (OrderItem $item) => [$item->id => round((float) $item->line_total, 2)]
            )->all();
        }

        $allocated = [];
        $running = 0.0;
        $lastId = $order->items->last()?->id;

        foreach ($order->items as $item) {
            $line = (float) $item->line_total;
            $lineDiscount = $item->id === $lastId
                ? max(0, $discount - $running)
                : round($discount * ($line / $subtotal), 2);

            $running += $lineDiscount;
            $allocated[$item->id] = max(0, round($line - $lineDiscount, 2));
        }

        return $allocated;
    }
}
