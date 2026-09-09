<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Finance\CostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderCostSnapshots extends Command
{
    protected $signature = 'costing:backfill-order-costs
        {--location= : Location ID}
        {--from= : Confirmed date from YYYY-MM-DD}
        {--to= : Confirmed date to YYYY-MM-DD}
        {--chunk=100 : Chunk size}
        {--dry-run : Count candidates without changing data}';

    protected $description = 'Backfill missing order item cost snapshots from linked stock movements only.';

    public function handle(CostingService $costing): int
    {
        $query = Order::query()
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereHas('items', fn ($q) => $q->whereNull('cost_snapshotted_at'));

        if ($this->option('location')) {
            $query->where('location_id', (int) $this->option('location'));
        }

        if ($this->option('from')) {
            $query->whereDate(DB::raw('COALESCE(confirmed_at, created_at)'), '>=', $this->option('from'));
        }

        if ($this->option('to')) {
            $query->whereDate(DB::raw('COALESCE(confirmed_at, created_at)'), '<=', $this->option('to'));
        }

        $candidateOrders = (clone $query)->count();
        $candidateItems = (clone $query)
            ->withCount(['items as missing_cost_items_count' => fn ($q) => $q->whereNull('cost_snapshotted_at')])
            ->get()
            ->sum('missing_cost_items_count');

        $this->info("Candidate orders: {$candidateOrders}; missing item snapshots: {$candidateItems}");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $done = 0;
        $missing = 0;
        $chunk = max(10, min(1000, (int) $this->option('chunk')));

        $query->with('items')->orderBy('id')->chunkById($chunk, function ($orders) use ($costing, &$done, &$missing): void {
            foreach ($orders as $order) {
                $result = $costing->backfillFromStockMovements($order);
                $done += $result['snapshotted'];
                $missing += count($result['missing_costs']);
            }
        });

        $this->info("Backfilled: {$done}; still missing reliable cost: {$missing}");

        return self::SUCCESS;
    }
}
