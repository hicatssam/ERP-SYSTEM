<?php

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CurrencyConversionService $currencies,
        private readonly ProcurementNotifier $notifier,
        private readonly PurchaseUnitConverter $purchaseUnits,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor) {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);

            if (! $supplier->isTransactable()) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'لا يمكن إنشاء أمر شراء لمورد غير نشط أو موقوف.',
                ]);
            }

            $rate = $this->currencies->resolveRate(
                (int) $data['currency_id'],
                $data['exchange_rate'] ?? null,
                Carbon::parse($data['order_date']),
            );

            $totals = $this->totals($data['items'], $rate, $data, $supplier);

            $order = PurchaseOrder::query()->create([
                'purchase_order_number' => $this->numbers->next('purchase_order', 'PO'),
                'supplier_id' => $supplier->id,
                'location_id' => $data['location_id'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $rate,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => PurchaseOrderStatus::Draft,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_cost' => $totals['shipping_cost'],
                'grand_total' => $totals['grand_total'],
                'base_grand_total' => $totals['base_grand_total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($totals['items'] as $item) {
                $order->items()->create($item);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_order.created',
                module: 'procurement',
                recordType: 'purchase_orders',
                recordId: $order->id,
                oldValues: null,
                newValues: $order->only([
                    'purchase_order_number', 'supplier_id', 'location_id', 'currency_id',
                    'status', 'grand_total',
                ]),
                metadata: ['items_count' => count($totals['items'])],
            );

            DB::afterCommit(function () use ($order, $supplier): void {
                $this->notifier->send(
                    type: 'purchase_order_created',
                    title: 'أمر شراء جديد',
                    message: "تم إنشاء أمر الشراء {$order->purchase_order_number} للمورد {$supplier->name}.",
                    url: route('purchase-orders.show', $order),
                    priority: 'medium',
                    locationId: $order->location_id,
                    permissions: ['purchase_orders.view', 'purchase_orders.approve'],
                    extra: ['reference_type' => 'purchase_orders', 'reference_id' => $order->id],
                );
            });

            return $order->load(['items.product', 'supplier', 'location', 'currency']);
        });
    }

    public function submit(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->statusValue() !== PurchaseOrderStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'status' => 'يمكن إرسال أمر الشراء من حالة المسودة فقط.',
                ]);
            }

            $order->update(['status' => PurchaseOrderStatus::Submitted]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_order.submitted',
                module: 'procurement',
                recordType: 'purchase_orders',
                recordId: $order->id,
                oldValues: ['status' => PurchaseOrderStatus::Draft->value],
                newValues: ['status' => PurchaseOrderStatus::Submitted->value],
                metadata: [],
            );

            DB::afterCommit(function () use ($order): void {
                $this->notifier->send(
                    type: 'purchase_order_submitted',
                    title: 'أمر شراء بانتظار الاعتماد',
                    message: "أمر الشراء {$order->purchase_order_number} بانتظار الاعتماد.",
                    url: route('purchase-orders.show', $order),
                    priority: 'high',
                    locationId: $order->location_id,
                    permissions: ['purchase_orders.approve'],
                    extra: ['reference_type' => 'purchase_orders', 'reference_id' => $order->id],
                );
            });

            return $order->fresh(['items.product', 'supplier', 'location', 'currency']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(PurchaseOrder $purchaseOrder, array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $actor) {
            $order = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($order->statusValue() !== PurchaseOrderStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تعديل أمر شراء بعد إرساله للاعتماد.',
                ]);
            }

            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            if (! $supplier->isTransactable()) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'لا يمكن استخدام مورد غير نشط أو موقوف.',
                ]);
            }

            $rate = $this->currencies->resolveRate(
                (int) $data['currency_id'],
                $data['exchange_rate'] ?? null,
                Carbon::parse($data['order_date']),
            );
            $totals = $this->totals($data['items'], $rate, $data, $supplier);
            $before = $order->only([
                'supplier_id', 'location_id', 'currency_id', 'exchange_rate', 'grand_total', 'notes',
            ]);

            $order->update([
                'supplier_id' => $supplier->id,
                'location_id' => $data['location_id'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $rate,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_cost' => $totals['shipping_cost'],
                'grand_total' => $totals['grand_total'],
                'base_grand_total' => $totals['base_grand_total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->delete();
            foreach ($totals['items'] as $item) {
                $order->items()->create($item);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_order.updated',
                module: 'procurement',
                recordType: 'purchase_orders',
                recordId: $order->id,
                oldValues: $before,
                newValues: $order->only([
                    'supplier_id', 'location_id', 'currency_id', 'exchange_rate', 'grand_total', 'notes',
                ]),
                metadata: ['items_count' => count($totals['items'])],
            );

            return $order->fresh(['items.product', 'supplier', 'location', 'currency']);
        });
    }

    public function approve(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->statusValue() !== PurchaseOrderStatus::Submitted->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن اعتماد أمر الشراء قبل إرساله للاعتماد.',
                ]);
            }

            $order->update([
                'status' => PurchaseOrderStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_order.approved',
                module: 'procurement',
                recordType: 'purchase_orders',
                recordId: $order->id,
                oldValues: ['status' => PurchaseOrderStatus::Submitted->value],
                newValues: ['status' => PurchaseOrderStatus::Approved->value],
                metadata: [],
            );

            DB::afterCommit(function () use ($order): void {
                $this->notifier->send(
                    type: 'purchase_order_approved',
                    title: 'تم اعتماد أمر الشراء',
                    message: "تم اعتماد أمر الشراء {$order->purchase_order_number} وهو جاهز للاستلام.",
                    url: route('purchase-orders.show', $order),
                    priority: 'medium',
                    locationId: $order->location_id,
                    permissions: ['purchase_orders.view', 'goods_receipts.create'],
                    extra: ['reference_type' => 'purchase_orders', 'reference_id' => $order->id],
                );
            });

            return $order->fresh(['items.product', 'supplier', 'location', 'currency']);
        });
    }

    public function cancel(PurchaseOrder $order, User $actor, ?string $reason = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            $order = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if (in_array($order->statusValue(), [PurchaseOrderStatus::Received->value, PurchaseOrderStatus::Cancelled->value], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء أمر شراء مستلم بالكامل أو ملغى سابقاً.',
                ]);
            }

            if ($order->items->sum(fn (PurchaseOrderItem $item): float => (float) $item->received_quantity) > 0) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء أمر شراء يحتوي كميات مستلمة. استخدم إرجاع مشتريات للكميات المستلمة.',
                ]);
            }

            $oldStatus = $order->statusValue();
            $order->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'purchase_order.cancelled',
                module: 'procurement',
                recordType: 'purchase_orders',
                recordId: $order->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => PurchaseOrderStatus::Cancelled->value],
                metadata: ['reason' => $reason],
            );

            return $order->fresh(['items.product', 'supplier', 'location', 'currency']);
        });
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $data */
    private function totals(
        array $items,
        string $exchangeRate,
        array $data,
        Supplier $supplier
    ): array
    {
        $normalisedItems = [];
        $subtotal = 0.0;
        $lineDiscount = 0.0;
        $lineTax = 0.0;

        $supplierProducts = SupplierProduct::query()
            ->with('purchaseUnit')
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->whereIn('product_id', collect($items)->pluck('product_id')->filter()->unique())
            ->get()
            ->keyBy('product_id');

        foreach ($items as $row) {
            $product = Product::query()->findOrFail($row['product_id']);
            $supplierProduct = $supplierProducts->get($product->id);

            if (! $supplierProduct) {
                throw ValidationException::withMessages([
                    'items' => "المنتج {$product->name_ar} غير مرتبط بالمورد المحدد.",
                ]);
            }

            $quantity = round((float) $row['ordered_quantity'], 3);
            $unitPrice = round((float) $row['unit_price'], 4);
            $conversionFactor = $this->purchaseUnits->factor($supplierProduct->conversion_factor);
            $orderedBaseQuantity = $this->purchaseUnits->baseQuantity($quantity, $conversionFactor);
            $discount = round((float) ($row['discount_amount'] ?? 0), 2);
            $tax = round((float) ($row['tax_amount'] ?? 0), 2);

            if ($quantity <= 0 || $unitPrice < 0 || $discount < 0 || $tax < 0) {
                throw ValidationException::withMessages([
                    'items' => 'تحقق من الكمية والسعر والضريبة والخصم في كل بند.',
                ]);
            }

            $gross = $quantity * $unitPrice;
            $lineTotal = max(0, $gross - $discount + $tax);
            $baseUnitCost = (float) $this->currencies->toBase(
                $this->purchaseUnits->pricePerBaseUnit($unitPrice, $conversionFactor),
                $exchangeRate
            );

            $normalisedItems[] = [
                'product_id' => $product->id,
                'supplier_product_id' => $supplierProduct->id,
                'purchase_unit_id' => $supplierProduct->purchase_unit_id,
                'supplier_sku_snapshot' => $supplierProduct->supplier_sku,
                'purchase_unit_snapshot' => $supplierProduct->purchaseUnit?->displayName()
                    ?? $product->unitDefinition?->displayName()
                    ?? $product->unit,
                'conversion_factor' => $conversionFactor,
                'description' => $row['description'] ?? $product->name_ar ?? $product->name,
                'ordered_quantity' => $quantity,
                'ordered_base_quantity' => $orderedBaseQuantity,
                'received_quantity' => 0,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'line_total' => $lineTotal,
                'base_unit_cost' => $baseUnitCost,
                'base_line_total' => (float) $this->currencies->toBase($lineTotal, $exchangeRate),
                'notes' => $row['notes'] ?? null,
            ];

            $subtotal += $gross;
            $lineDiscount += $discount;
            $lineTax += $tax;
        }

        $headerDiscount = round((float) ($data['discount_amount'] ?? 0), 2);
        $headerTax = round((float) ($data['tax_amount'] ?? 0), 2);
        $shipping = round((float) ($data['shipping_cost'] ?? 0), 2);
        $discount = $lineDiscount + $headerDiscount;
        $tax = $lineTax + $headerTax;
        $grandTotal = max(0, $subtotal - $discount + $tax + $shipping);

        return [
            'items' => $normalisedItems,
            'subtotal' => $this->currencies->money($subtotal),
            'discount_amount' => $this->currencies->money($discount),
            'tax_amount' => $this->currencies->money($tax),
            'shipping_cost' => $this->currencies->money($shipping),
            'grand_total' => $this->currencies->money($grandTotal),
            'base_grand_total' => $this->currencies->money(
                $this->currencies->toBase($grandTotal, $exchangeRate)
            ),
        ];
    }
}
