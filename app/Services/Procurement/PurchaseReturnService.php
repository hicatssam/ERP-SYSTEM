<?php

namespace App\Services\Procurement;

use App\Enums\GoodsReceiptStatus;
use App\Enums\MovementReason;
use App\Enums\PurchaseReturnStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles returns to suppliers as their own documents.  It deliberately does
 * not reuse customer credit notes: posting a return reduces stock, records an
 * immutable ledger movement, and optionally credits the linked supplier invoice.
 */
class PurchaseReturnService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CurrencyConversionService $currencies,
        private readonly InventoryService $inventory,
        private readonly SupplierInvoiceService $invoices,
        private readonly ProcurementNotifier $notifier,
        private readonly PurchaseUnitConverter $purchaseUnits,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $actor) {
            $receipt = GoodsReceipt::query()
                ->with(['items.product', 'items.batch'])
                ->lockForUpdate()
                ->findOrFail($data['goods_receipt_id']);

            if ($receipt->statusValue() !== GoodsReceiptStatus::Posted->value) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'لا يمكن إنشاء مرتجع إلا من سند استلام مُرحّل إلى المخزون.',
                ]);
            }

            $invoice = $this->resolveInvoice($data, $receipt);
            $itemsById = $receipt->items->keyBy('id');
            $return = PurchaseReturn::query()->create([
                'return_number' => $this->numbers->next('purchase_return', 'PRT'),
                'supplier_id' => $receipt->supplier_id,
                'goods_receipt_id' => $receipt->id,
                'supplier_invoice_id' => $invoice?->id,
                'location_id' => $receipt->location_id,
                'currency_id' => $receipt->currency_id,
                'exchange_rate' => $receipt->exchange_rate,
                'status' => PurchaseReturnStatus::Draft,
                'grand_total' => 0,
                'base_grand_total' => 0,
                'returned_by' => $actor->id,
                'returned_at' => $data['returned_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $total = 0.0;
            $baseTotal = 0.0;
            $hasReturnItem = false;

            foreach ($data['items'] as $row) {
                $receiptItem = $itemsById->get((int) $row['goods_receipt_item_id']);

                if (! $receiptItem) {
                    throw ValidationException::withMessages([
                        'items' => 'أحد بنود المرتجع لا يخص سند الاستلام المحدد.',
                    ]);
                }

                $quantity = $this->quantity($row['return_quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $this->assertReturnableQuantity($receiptItem, $quantity);

                $conversionFactor = $this->purchaseUnits->factor($receiptItem->conversion_factor);
                $returnBaseQuantity = $this->purchaseUnits->baseQuantity($quantity, $conversionFactor);

                $batch = $this->resolveBatch($row, $receiptItem, $receipt);
                $lineTotal = $this->currencies->money($quantity * (float) $receiptItem->unit_cost);
                $baseLineTotal = $this->currencies->money(
                    $returnBaseQuantity * (float) $receiptItem->base_unit_cost
                );

                $return->items()->create([
                    'goods_receipt_item_id' => $receiptItem->id,
                    'inventory_batch_id' => $batch?->id,
                    'product_id' => $receiptItem->product_id,
                    'return_quantity' => $quantity,
                    'conversion_factor' => $conversionFactor,
                    'return_base_quantity' => $returnBaseQuantity,
                    'unit_cost' => $receiptItem->unit_cost,
                    'base_unit_cost' => $receiptItem->base_unit_cost,
                    'line_total' => $lineTotal,
                    'base_line_total' => $baseLineTotal,
                    'reason' => $row['reason'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);

                $total += (float) $lineTotal;
                $baseTotal += (float) $baseLineTotal;
                $hasReturnItem = true;
            }

            if (! $hasReturnItem) {
                throw ValidationException::withMessages([
                    'items' => 'أدخل كمية مرتجع أكبر من صفر لصنف واحد على الأقل.',
                ]);
            }

            $return->update([
                'grand_total' => $this->currencies->money($total),
                'base_grand_total' => $this->currencies->money($baseTotal),
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_return.created',
                module: 'procurement',
                recordType: 'purchase_returns',
                recordId: $return->id,
                oldValues: null,
                newValues: $return->only(['return_number', 'supplier_id', 'goods_receipt_id', 'status', 'grand_total']),
                metadata: ['items_count' => $return->items()->count()],
            );

            return $return->load([
                'items.product', 'items.goodsReceiptItem', 'items.inventoryBatch',
                'supplier', 'goodsReceipt', 'supplierInvoice', 'location', 'currency',
            ]);
        });
    }

    public function post(PurchaseReturn $purchaseReturn, User $actor): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturn, $actor) {
            $return = PurchaseReturn::query()
                ->with(['items.goodsReceiptItem', 'items.inventoryBatch', 'items.product', 'supplierInvoice'])
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            if ($return->statusValue() !== PurchaseReturnStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن ترحيل مرتجع تم ترحيله أو إلغاؤه مسبقاً.',
                ]);
            }

            $this->assertInvoiceCreditDoesNotExceedLiability($return);

            foreach ($return->items as $item) {
                // Lock the originating receipt line before calculating the
                // cumulative returned quantity. Without this lock, two return
                // documents posted at the same time could both pass the
                // validation and return more than was accepted.
                $receiptItem = GoodsReceiptItem::query()
                    ->whereKey($item->goods_receipt_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertReturnableQuantity($receiptItem, (float) $item->return_quantity, $return->id);

                $batch = $item->inventoryBatch
                    ? InventoryBatch::query()->whereKey($item->inventory_batch_id)->lockForUpdate()->firstOrFail()
                    : null;

                $returnBaseQuantity = (float) ($item->return_base_quantity
                    ?: $this->purchaseUnits->baseQuantity(
                        $item->return_quantity,
                        $item->conversion_factor
                    ));

                if ($batch && $returnBaseQuantity > (float) $batch->available_quantity + 0.0005) {
                    throw ValidationException::withMessages([
                        'items' => "كمية مرتجع الدفعة {$batch->batch_number} أكبر من رصيد الدفعة المتاح.",
                    ]);
                }

                $this->inventory->decrease(
                    locationId: $return->location_id,
                    productId: $item->product_id,
                    quantity: $returnBaseQuantity,
                    reason: MovementReason::PurchaseReturn,
                    userId: $actor->id,
                    referenceType: 'purchase_returns',
                    referenceId: $return->id,
                    idempotencyKey: "purchase-return:{$return->id}:{$item->id}",
                    note: $item->notes,
                    unitCost: (string) round(
                        $this->purchaseUnits->pricePerBaseUnit(
                            $item->unit_cost,
                            $item->conversion_factor
                        ), 4
                    ),
                    currencyId: $return->currency_id,
                    exchangeRate: (string) $return->exchange_rate,
                    inventoryBatchId: $batch?->id,
                );

                if ($batch) {
                    $batch->decrement('available_quantity', $returnBaseQuantity);
                }
            }

            $return->update([
                'status' => PurchaseReturnStatus::Posted,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            if ($return->supplierInvoice) {
                $this->invoices->syncPaymentAmounts($return->supplierInvoice);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_return.posted',
                module: 'procurement',
                recordType: 'purchase_returns',
                recordId: $return->id,
                oldValues: ['status' => PurchaseReturnStatus::Draft->value],
                newValues: ['status' => PurchaseReturnStatus::Posted->value],
                metadata: [
                    'goods_receipt_id' => $return->goods_receipt_id,
                    'supplier_invoice_id' => $return->supplier_invoice_id,
                    'grand_total' => $return->grand_total,
                ],
            );

            DB::afterCommit(function () use ($return): void {
                $this->notifier->send(
                    type: 'purchase_return_posted',
                    title: 'تم ترحيل مرتجع مورد',
                    message: "تم ترحيل مرتجع المورد {$return->return_number} من مخزون الموقع.",
                    url: route('purchase-returns.show', $return),
                    priority: 'high',
                    locationId: $return->location_id,
                    permissions: ['purchase_returns.view', 'inventory.view', 'supplier_invoices.view'],
                    extra: ['reference_type' => 'purchase_returns', 'reference_id' => $return->id],
                );
            });

            return $return->fresh([
                'items.product', 'items.goodsReceiptItem', 'items.inventoryBatch',
                'supplier', 'goodsReceipt', 'supplierInvoice', 'location', 'currency', 'returner', 'poster',
            ]);
        });
    }

    public function cancel(PurchaseReturn $purchaseReturn, User $actor): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturn, $actor) {
            $return = PurchaseReturn::query()->lockForUpdate()->findOrFail($purchaseReturn->id);

            if ($return->statusValue() !== PurchaseReturnStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء مرتجع مُرحّل. أنشئ حركة عكسية موثقة بدلاً من ذلك.',
                ]);
            }

            $return->update(['status' => PurchaseReturnStatus::Cancelled]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_return.cancelled',
                module: 'procurement',
                recordType: 'purchase_returns',
                recordId: $return->id,
                oldValues: ['status' => PurchaseReturnStatus::Draft->value],
                newValues: ['status' => PurchaseReturnStatus::Cancelled->value],
                metadata: [],
            );

            return $return->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveInvoice(array $data, GoodsReceipt $receipt): ?SupplierInvoice
    {
        if (empty($data['supplier_invoice_id'])) {
            return null;
        }

        $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($data['supplier_invoice_id']);

        if (
            (int) $invoice->supplier_id !== (int) $receipt->supplier_id
            || (int) $invoice->location_id !== (int) $receipt->location_id
            || (int) $invoice->currency_id !== (int) $receipt->currency_id
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => 'فاتورة المورد المختارة لا تطابق المورد أو الموقع أو العملة الخاصة بسند الاستلام.',
            ]);
        }

        return $invoice;
    }

    /** @param array<string, mixed> $row */
    private function resolveBatch(array $row, GoodsReceiptItem $receiptItem, GoodsReceipt $receipt): ?InventoryBatch
    {
        $batchId = $row['inventory_batch_id'] ?? $receiptItem->batch?->id;

        if (! $batchId) {
            return null;
        }

        $batch = InventoryBatch::query()->findOrFail($batchId);

        if (
            (int) $batch->product_id !== (int) $receiptItem->product_id
            || (int) $batch->location_id !== (int) $receipt->location_id
            || (int) $batch->goods_receipt_item_id !== (int) $receiptItem->id
        ) {
            throw ValidationException::withMessages([
                'items' => 'الدفعة المختارة لا تخص بند الاستلام أو موقعه.',
            ]);
        }

        return $batch;
    }

    private function assertReturnableQuantity(GoodsReceiptItem $receiptItem, float $quantity, ?int $ignoreReturnId = null): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'items' => 'كمية المرتجع يجب أن تكون أكبر من صفر.',
            ]);
        }

        $alreadyReturned = (float) PurchaseReturnItem::query()
            ->where('goods_receipt_item_id', $receiptItem->id)
            ->when($ignoreReturnId, fn ($query) => $query->where('purchase_return_id', '!=', $ignoreReturnId))
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', PurchaseReturnStatus::Posted->value))
            ->sum('return_quantity');

        if ($quantity > (float) $receiptItem->accepted_quantity - $alreadyReturned + 0.0005) {
            throw ValidationException::withMessages([
                'items' => 'كمية المرتجع تتجاوز الكمية المقبولة المتبقية من سند الاستلام.',
            ]);
        }
    }

    private function assertInvoiceCreditDoesNotExceedLiability(PurchaseReturn $return): void
    {
        if (! $return->supplier_invoice_id) {
            return;
        }

        $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($return->supplier_invoice_id);
        $existingCredits = (float) PurchaseReturn::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->where('id', '!=', $return->id)
            ->sum('grand_total');
        $payments = (float) SupplierPayment::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('status', 'confirmed')
            ->sum('applied_amount');

        if ($existingCredits + $payments + (float) $return->grand_total > (float) $invoice->grand_total + 0.004) {
            throw ValidationException::withMessages([
                'supplier_invoice_id' => 'قيمة المرتجع والدفعات تتجاوز قيمة فاتورة المورد.',
            ]);
        }
    }

    private function quantity(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 3);
    }
}
