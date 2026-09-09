<?php

namespace App\Services\Procurement;

use App\Enums\GoodsReceiptStatus;
use App\Enums\MovementReason;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierProduct;
use App\Models\SupplierProductPriceHistory;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CurrencyConversionService $currencies,
        private readonly InventoryService $inventory,
        private readonly ProcurementNotifier $notifier,
        private readonly PurchaseUnitConverter $purchaseUnits,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $actor) {
            $order = PurchaseOrder::query()
                ->with('items.product')
                ->lockForUpdate()
                ->findOrFail($data['purchase_order_id']);

            if (! $order->isReceivable()) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'يمكن إنشاء سند استلام لأمر شراء معتمد أو مستلم جزئياً فقط.',
                ]);
            }

            $receipt = GoodsReceipt::query()->create([
                'receipt_number' => $this->numbers->next('goods_receipt', 'GRN'),
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'location_id' => $order->location_id,
                'currency_id' => $order->currency_id,
                'exchange_rate' => $order->exchange_rate,
                'status' => GoodsReceiptStatus::Draft,
                'received_by' => $actor->id,
                'received_at' => $data['received_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $itemsById = $order->items->keyBy('id');

            foreach ($data['items'] as $row) {
                $purchaseOrderItem = $itemsById->get((int) $row['purchase_order_item_id']);

                if (! $purchaseOrderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'أحد بنود الاستلام لا يخص أمر الشراء المحدد.',
                    ]);
                }

                $received = $this->quantity($row['received_quantity']);
                $accepted = $this->quantity($row['accepted_quantity']);
                $rejected = $this->quantity($row['rejected_quantity'] ?? 0);

                $this->assertReceiptQuantities($received, $accepted, $rejected, $purchaseOrderItem);

                $unitCost = round((float) ($row['unit_cost'] ?? $purchaseOrderItem->unit_price), 4);
                $conversionFactor = $this->purchaseUnits->factor($purchaseOrderItem->conversion_factor);
                $receivedBase = $this->purchaseUnits->baseQuantity($received, $conversionFactor);
                $acceptedBase = $this->purchaseUnits->baseQuantity($accepted, $conversionFactor);
                $rejectedBase = $this->purchaseUnits->baseQuantity($rejected, $conversionFactor);

                $receipt->items()->create([
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'product_id' => $purchaseOrderItem->product_id,
                    'purchase_unit_id' => $purchaseOrderItem->purchase_unit_id,
                    'purchase_unit_snapshot' => $purchaseOrderItem->purchase_unit_snapshot,
                    'conversion_factor' => $conversionFactor,
                    'ordered_quantity' => $purchaseOrderItem->ordered_quantity,
                    'received_quantity' => $received,
                    'received_base_quantity' => $receivedBase,
                    'accepted_quantity' => $accepted,
                    'accepted_base_quantity' => $acceptedBase,
                    'rejected_quantity' => $rejected,
                    'rejected_base_quantity' => $rejectedBase,
                    'unit_cost' => $unitCost,
                    'base_unit_cost' => $this->currencies->toBase(
                        $this->purchaseUnits->pricePerBaseUnit($unitCost, $conversionFactor),
                        $order->exchange_rate
                    ),
                    'line_total' => $this->currencies->money($accepted * $unitCost),
                    'base_line_total' => $this->currencies->money(
                        $this->currencies->toBase($accepted * $unitCost, $order->exchange_rate)
                    ),
                    'batch_number' => $row['batch_number'] ?? null,
                    'manufacturing_date' => $row['manufacturing_date'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'goods_receipt.created',
                module: 'procurement',
                recordType: 'goods_receipts',
                recordId: $receipt->id,
                oldValues: null,
                newValues: $receipt->only(['receipt_number', 'purchase_order_id', 'location_id', 'status']),
                metadata: ['items_count' => $receipt->items()->count()],
            );

            return $receipt->load(['items.product', 'purchaseOrder', 'supplier', 'location', 'currency']);
        });
    }

    public function post(GoodsReceipt $goodsReceipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceipt, $actor) {
            $receipt = GoodsReceipt::query()
                ->with(['items.product', 'purchaseOrder.items'])
                ->lockForUpdate()
                ->findOrFail($goodsReceipt->id);

            if ($receipt->statusValue() !== GoodsReceiptStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن ترحيل سند استلام تم ترحيله أو إلغاؤه مسبقاً.',
                ]);
            }

            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($receipt->purchase_order_id);

            if (! $purchaseOrder->isReceivable()) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'أمر الشراء غير متاح للاستلام حالياً.',
                ]);
            }

            $acceptedTotal = 0.0;
            $rejectedTotal = 0.0;

            foreach ($receipt->items as $receiptItem) {
                $purchaseOrderItem = PurchaseOrderItem::query()
                    ->whereKey($receiptItem->purchase_order_item_id)
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $received = $this->quantity($receiptItem->received_quantity);
                $accepted = $this->quantity($receiptItem->accepted_quantity);
                $rejected = $this->quantity($receiptItem->rejected_quantity);
                $acceptedBase = $this->quantity(
                    $receiptItem->accepted_base_quantity
                        ?: $this->purchaseUnits->baseQuantity($accepted, $receiptItem->conversion_factor)
                );
                $this->assertReceiptQuantities($received, $accepted, $rejected, $purchaseOrderItem);

                $baseUnitCost = (string) $receiptItem->base_unit_cost;

                $batch = $this->createBatchWhenRequired($receipt, $receiptItem, $baseUnitCost);

                if ($accepted > 0) {
                    $this->inventory->increase(
                        locationId: $receipt->location_id,
                        productId: $receiptItem->product_id,
                        quantity: $acceptedBase,
                        reason: MovementReason::PurchaseReceipt,
                        userId: $actor->id,
                        referenceType: 'goods_receipts',
                        referenceId: $receipt->id,
                        unitCost: (string) round(
                            $this->purchaseUnits->pricePerBaseUnit(
                                $receiptItem->unit_cost,
                                $receiptItem->conversion_factor
                            ), 4
                        ),
                        currencyId: $receipt->currency_id,
                        exchangeRate: (string) $receipt->exchange_rate,
                        inventoryBatchId: $batch?->id,
                        idempotencyKey: "goods-receipt:{$receipt->id}:{$receiptItem->id}",
                        note: $receiptItem->notes,
                    );

                    $this->recordSupplierPrice($receipt, $receiptItem, $actor);
                }

                // Only the accepted quantity fulfils the purchase-order line.
                // Rejected units never enter inventory and must remain open for
                // a replacement delivery or a documented cancellation.
                $purchaseOrderItem->increment('received_quantity', $accepted);

                $acceptedTotal += $accepted;
                $rejectedTotal += $rejected;
            }

            $this->refreshPurchaseOrderStatus($purchaseOrder);

            $receipt->update([
                'status' => GoodsReceiptStatus::Posted,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'goods_receipt.posted',
                module: 'procurement',
                recordType: 'goods_receipts',
                recordId: $receipt->id,
                oldValues: ['status' => GoodsReceiptStatus::Draft->value],
                newValues: ['status' => GoodsReceiptStatus::Posted->value],
                metadata: [
                    'purchase_order_id' => $purchaseOrder->id,
                    'accepted_quantity' => $acceptedTotal,
                    'rejected_quantity' => $rejectedTotal,
                ],
            );

            DB::afterCommit(function () use ($receipt, $acceptedTotal, $rejectedTotal): void {
                $message = "تم ترحيل سند الاستلام {$receipt->receipt_number} إلى مخزون الموقع.";

                if ($rejectedTotal > 0) {
                    $message .= " كمية مرفوضة: {$rejectedTotal}.";
                }

                $this->notifier->send(
                    type: $rejectedTotal > 0 ? 'goods_received_with_rejections' : 'goods_received',
                    title: $rejectedTotal > 0 ? 'استلام بكمية مرفوضة' : 'تم استلام مشتريات',
                    message: $message,
                    url: route('goods-receipts.show', $receipt),
                    priority: $rejectedTotal > 0 ? 'high' : 'medium',
                    locationId: $receipt->location_id,
                    permissions: ['goods_receipts.view', 'purchase_orders.view', 'inventory.view'],
                    extra: [
                        'reference_type' => 'goods_receipts',
                        'reference_id' => $receipt->id,
                        'accepted_quantity' => $acceptedTotal,
                        'rejected_quantity' => $rejectedTotal,
                    ],
                );
            });

            return $receipt->fresh([
                'items.product', 'items.purchaseOrderItem', 'purchaseOrder.items',
                'supplier', 'location', 'currency', 'receiver', 'poster',
            ]);
        });
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $items = PurchaseOrderItem::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->get(['ordered_quantity', 'received_quantity']);

        $allReceived = $items->isNotEmpty()
            && $items->every(fn (PurchaseOrderItem $item): bool =>
                (float) $item->received_quantity >= (float) $item->ordered_quantity
            );

        $hasReceived = $items->contains(fn (PurchaseOrderItem $item): bool =>
            (float) $item->received_quantity > 0
        );

        $purchaseOrder->update([
            'status' => $allReceived
                ? PurchaseOrderStatus::Received
                : ($hasReceived ? PurchaseOrderStatus::PartiallyReceived : PurchaseOrderStatus::Approved),
        ]);
    }

    private function createBatchWhenRequired(GoodsReceipt $receipt, GoodsReceiptItem $item, string $baseUnitCost): ?InventoryBatch
    {
        if ((float) $item->accepted_base_quantity <= 0) {
            return null;
        }

        $product = $item->product;
        $hasBatchData = filled($item->batch_number)
            || $item->manufacturing_date !== null
            || $item->expiry_date !== null;

        if (! $product->tracks_batch && ! $product->tracks_expiry && ! $hasBatchData) {
            return null;
        }

        if ($product->tracks_batch && blank($item->batch_number)) {
            throw ValidationException::withMessages([
                'items' => "المنتج {$product->name_ar} يتطلب رقم دفعة.",
            ]);
        }

        if ($product->tracks_expiry && $item->expiry_date === null) {
            throw ValidationException::withMessages([
                'items' => "المنتج {$product->name_ar} يتطلب تاريخ صلاحية.",
            ]);
        }

        return InventoryBatch::query()->create([
            'goods_receipt_item_id' => $item->id,
            'product_id' => $item->product_id,
            'location_id' => $receipt->location_id,
            'batch_number' => $item->batch_number,
            'manufacturing_date' => $item->manufacturing_date,
            'expiry_date' => $item->expiry_date,
            'received_quantity' => $item->accepted_base_quantity,
            'available_quantity' => $item->accepted_base_quantity,
            'unit_cost' => round(
                $this->purchaseUnits->pricePerBaseUnit($item->unit_cost, $item->conversion_factor), 4
            ),
            'base_unit_cost' => $baseUnitCost,
            'currency_id' => $receipt->currency_id,
        ]);
    }

    private function recordSupplierPrice(
        GoodsReceipt $receipt,
        GoodsReceiptItem $item,
        User $actor
    ): void
    {
        $supplierProduct = SupplierProduct::query()->firstOrCreate(
            [
                'supplier_id' => $receipt->supplier_id,
                'product_id' => $item->product_id,
            ],
            [
                'purchase_price' => $item->unit_cost,
                'currency_id' => $receipt->currency_id,
                'minimum_order_quantity' => 1,
                'is_active' => true,
            ],
        );

        $supplierProduct->update([
            'purchase_price' => $item->unit_cost,
            'currency_id' => $receipt->currency_id,
            'purchase_unit_id' => $item->purchase_unit_id,
            'conversion_factor' => $item->conversion_factor,
            'is_active' => true,
        ]);

        SupplierProductPriceHistory::query()->create([
            'supplier_product_id' => $supplierProduct->id,
            'purchase_price' => $item->unit_cost,
            'currency_id' => $receipt->currency_id,
            'purchase_unit_id' => $item->purchase_unit_id,
            'purchase_unit_snapshot' => $item->purchase_unit_snapshot,
            'conversion_factor' => $item->conversion_factor,
            'exchange_rate' => $receipt->exchange_rate,
            'base_purchase_price' => $this->currencies->toBase(
                (string) $item->unit_cost,
                (string) $receipt->exchange_rate
            ),
            'reference_type' => 'goods_receipt_items',
            'reference_id' => $item->id,
            'effective_at' => $receipt->received_at,
            'created_by' => $actor->id,
        ]);
    }

    private function assertReceiptQuantities(float $received, float $accepted, float $rejected, PurchaseOrderItem $purchaseOrderItem): void
    {
        if ($received <= 0 || $accepted < 0 || $rejected < 0) {
            throw ValidationException::withMessages([
                'items' => 'كميات الاستلام يجب أن تكون صحيحة وأكبر من صفر.',
            ]);
        }

        if (abs(($accepted + $rejected) - $received) > 0.0005) {
            throw ValidationException::withMessages([
                'items' => 'الكمية المقبولة والمرفوضة يجب أن تساوي الكمية المستلمة.',
            ]);
        }

        if ($received > $purchaseOrderItem->remainingQuantity() + 0.0005) {
            throw ValidationException::withMessages([
                'items' => 'لا يمكن استلام كمية أكبر من الكمية المتبقية في أمر الشراء.',
            ]);
        }
    }

    private function quantity(float|string|int|null $value): float
    {
        return round((float) ($value ?? 0), 3);
    }
}
