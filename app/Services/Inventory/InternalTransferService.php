<?php

namespace App\Services\Inventory;

use App\Enums\MovementReason;
use App\Enums\StockRequestStatus;
use App\Enums\StockTransferStatus;
use App\Models\StockReceivingInvoice;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\TransferDiscrepancy;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Procurement\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates factory-to-branch stock transfers. A transfer is created once
 * from an accepted request, its dispatch decreases source on-hand and records
 * destination in-transit, then its receipt creates the destination movement.
 */
class InternalTransferService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly DocumentNumberService $numbers,
    ) {
    }

    /** @param array<int, float|int|string> $approvedQuantities */
    public function createFromRequest(StockRequest $stockRequest, array $approvedQuantities, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($stockRequest, $approvedQuantities, $actor) {
            $request = StockRequest::query()->with('items')->lockForUpdate()->findOrFail($stockRequest->id);

            if (! in_array($this->statusValue($request), [
                StockRequestStatus::Accepted->value,
                StockRequestStatus::PartiallyAccepted->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إنشاء تحويل إلا لطلب مخزون مقبول أو مقبول جزئياً.',
                ]);
            }

            if ($request->transfers()->exists()) {
                throw ValidationException::withMessages([
                    'stock_request' => 'تم إنشاء تحويل لهذا الطلب مسبقاً.',
                ]);
            }

            $items = [];
            foreach ($request->items as $item) {
                $approved = array_key_exists($item->id, $approvedQuantities)
                    ? $this->quantity($approvedQuantities[$item->id])
                    : $this->quantity($item->approved_quantity ?? $item->requested_quantity);

                if ($approved <= 0) {
                    continue;
                }

                if ($approved > (float) $item->requested_quantity + 0.0005) {
                    throw ValidationException::withMessages([
                        'items' => 'الكمية المعتمدة لا يمكن أن تتجاوز الكمية المطلوبة.',
                    ]);
                }

                $items[] = ['product_id' => $item->product_id, 'sent_quantity' => $approved];
                $item->update(['approved_quantity' => $approved]);
            }

            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'يجب اعتماد صنف واحد بكمية أكبر من صفر على الأقل.',
                ]);
            }

            $transfer = StockTransfer::query()->create([
                'transfer_number' => $this->numbers->next('stock_transfer', 'TRF'),
                'stock_request_id' => $request->id,
                'from_location_id' => $request->factory_location_id,
                'to_location_id' => $request->branch_location_id,
                'status' => StockTransferStatus::Draft,
            ]);

            foreach ($items as $item) {
                $transfer->items()->create($item);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'stock_transfer.created',
                module: 'inventory',
                recordType: 'stock_transfers',
                recordId: $transfer->id,
                oldValues: null,
                newValues: $transfer->only(['transfer_number', 'stock_request_id', 'from_location_id', 'to_location_id', 'status']),
                metadata: ['items_count' => count($items)],
            );

            return $transfer->load(['items.product', 'fromLocation', 'toLocation', 'stockRequest']);
        });
    }

    public function dispatch(StockTransfer $stockTransfer, User $actor, ?string $notes = null): StockTransfer
    {
        return DB::transaction(function () use ($stockTransfer, $actor, $notes) {
            $transfer = StockTransfer::query()->with('items')->lockForUpdate()->findOrFail($stockTransfer->id);

            if ($this->statusValue($transfer) !== StockTransferStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إرسال تحويل غير موجود في حالة المسودة.',
                ]);
            }

            foreach ($transfer->items as $item) {
                $sourceInventory = $this->inventory->decrease(
                    locationId: $transfer->from_location_id,
                    productId: $item->product_id,
                    quantity: (float) $item->sent_quantity,
                    reason: MovementReason::TransferDispatch,
                    userId: $actor->id,
                    referenceType: 'stock_transfers',
                    referenceId: $transfer->id,
                    idempotencyKey: "stock-transfer-dispatch:{$transfer->id}:{$item->id}",
                    note: $notes,
                );

                $item->update(['unit_cost' => $sourceInventory->unit_cost]);
                $this->inventory->addInTransit(
                    $transfer->to_location_id,
                    $item->product_id,
                    (float) $item->sent_quantity,
                    $actor->id,
                    'stock_transfers',
                    $transfer->id,
                );
            }

            $transfer->update([
                'status' => StockTransferStatus::Dispatched,
                'dispatch_notes' => $notes,
                'dispatched_by' => $actor->id,
                'dispatched_at' => now(),
            ]);
            $transfer->stockRequest?->update(['status' => StockRequestStatus::Dispatched]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'stock_transfer.dispatched',
                module: 'inventory',
                recordType: 'stock_transfers',
                recordId: $transfer->id,
                oldValues: ['status' => StockTransferStatus::Draft->value],
                newValues: ['status' => StockTransferStatus::Dispatched->value],
                metadata: ['transfer_number' => $transfer->transfer_number, 'items_count' => $transfer->items->count()],
            );

            return $transfer->fresh(['items.product', 'fromLocation', 'toLocation', 'stockRequest']);
        });
    }

    /** @param array<int, array{received_quantity: float|int|string, damaged_quantity?: float|int|string}> $items */
    public function receive(StockTransfer $stockTransfer, array $items, User $actor, ?string $notes = null): StockReceivingInvoice
    {
        return DB::transaction(function () use ($stockTransfer, $items, $actor, $notes) {
            $transfer = StockTransfer::query()->with(['items', 'stockRequest'])->lockForUpdate()->findOrFail($stockTransfer->id);

            if ($this->statusValue($transfer) !== StockTransferStatus::Dispatched->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن استلام تحويل غير مرسل.',
                ]);
            }

            $submitted = collect($items)->keyBy(fn (array $row, int|string $key): int => (int) $key);
            $discrepancyCount = 0;

            foreach ($transfer->items as $item) {
                $row = $submitted->get($item->id);
                if (! $row) {
                    throw ValidationException::withMessages([
                        'items' => 'يجب إدخال نتيجة الاستلام لكل بند في التحويل.',
                    ]);
                }

                $received = $this->quantity($row['received_quantity'] ?? 0);
                $damaged = $this->quantity($row['damaged_quantity'] ?? 0);
                $sent = $this->quantity($item->sent_quantity);

                if ($received < 0 || $damaged < 0 || $received + $damaged > $sent + 0.0005) {
                    throw ValidationException::withMessages([
                        'items' => 'المستلم والتالف لا يمكن أن يتجاوزا الكمية المرسلة.',
                    ]);
                }

                $item->update([
                    'received_quantity' => $received,
                    'damaged_quantity' => $damaged,
                ]);

                $this->inventory->clearInTransit(
                    $transfer->to_location_id,
                    $item->product_id,
                    $sent,
                    $actor->id,
                    'stock_transfers',
                    $transfer->id,
                );

                if ($received > 0) {
                    $this->inventory->increase(
                        locationId: $transfer->to_location_id,
                        productId: $item->product_id,
                        quantity: $received,
                        reason: MovementReason::TransferReceipt,
                        userId: $actor->id,
                        referenceType: 'stock_transfers',
                        referenceId: $transfer->id,
                        unitCost: (string) $item->unit_cost,
                        currencyId: $item->currency_id,
                        exchangeRate: '1',
                        idempotencyKey: "stock-transfer-receipt:{$transfer->id}:{$item->id}",
                        note: $notes,
                    );
                }

                $shortage = max(0, $sent - $received - $damaged);
                if ($damaged > 0) {
                    $this->recordDiscrepancy($transfer->id, $item->product_id, $sent, $received, $damaged, 'damage');
                    $discrepancyCount++;
                }
                if ($shortage > 0) {
                    $this->recordDiscrepancy($transfer->id, $item->product_id, $sent, $received, $shortage, 'shortage');
                    $discrepancyCount++;
                }
            }

            $status = $discrepancyCount > 0
                ? StockTransferStatus::DiscrepancyOpen
                : StockTransferStatus::Received;
            $transfer->update([
                'status' => $status,
                'received_by' => $actor->id,
                'received_at' => now(),
                'receiving_notes' => $notes,
            ]);
            $transfer->stockRequest?->update([
                'status' => $discrepancyCount > 0
                    ? StockRequestStatus::DiscrepancyOpen
                    : StockRequestStatus::Received,
            ]);

            $invoice = StockReceivingInvoice::query()->firstOrCreate(
                ['stock_transfer_id' => $transfer->id],
                [
                    'invoice_number' => $this->numbers->next('stock_receiving_invoice', 'STR'),
                    'received_by' => $actor->id,
                    'receiving_location_id' => $transfer->to_location_id,
                    'sending_location_id' => $transfer->from_location_id,
                    'total_items_ordered' => $transfer->items->sum('sent_quantity'),
                    'total_items_received' => $transfer->items->sum('received_quantity'),
                    'total_items_damaged' => $transfer->items->sum('damaged_quantity'),
                    'notes' => $notes,
                    'issued_at' => now(),
                ],
            );

            ActivityLogger::log(
                userId: $actor->id,
                action: 'stock_transfer.received',
                module: 'inventory',
                recordType: 'stock_transfers',
                recordId: $transfer->id,
                oldValues: ['status' => StockTransferStatus::Dispatched->value],
                newValues: ['status' => $status->value],
                metadata: [
                    'transfer_number' => $transfer->transfer_number,
                    'discrepancy_count' => $discrepancyCount,
                    'stock_receiving_invoice_id' => $invoice->id,
                ],
            );

            return $invoice;
        });
    }

    private function recordDiscrepancy(
        int $transferId,
        int $productId,
        float $sent,
        float $received,
        float $variance,
        string $type,
    ): void {
        TransferDiscrepancy::query()->create([
            'stock_transfer_id' => $transferId,
            'product_id' => $productId,
            'sent_quantity' => $sent,
            'received_quantity' => $received,
            'variance' => $variance,
            'discrepancy_type' => $type,
            'status' => 'open',
        ]);
    }

    private function statusValue(StockRequest|StockTransfer $model): string
    {
        return $model->status instanceof \BackedEnum
            ? $model->status->value
            : (string) $model->status;
    }

    private function quantity(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 3);
    }
}
