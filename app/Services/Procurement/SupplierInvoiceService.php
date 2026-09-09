<?php

namespace App\Services\Procurement;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseReturnStatus;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceService
{
    public function __construct(
        private readonly CurrencyConversionService $currencies,
        private readonly ProcurementNotifier $notifier,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): SupplierInvoice
    {
        return DB::transaction(function () use ($data, $actor) {
            $purchaseOrder = isset($data['purchase_order_id'])
                ? PurchaseOrder::query()->findOrFail($data['purchase_order_id'])
                : null;
            $goodsReceipt = isset($data['goods_receipt_id'])
                ? GoodsReceipt::query()->with('items.product')->findOrFail($data['goods_receipt_id'])
                : null;

            if ($purchaseOrder && $goodsReceipt) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'اختر أمر شراء أو سند استلام واحداً فقط كمصدر للفاتورة.',
                ]);
            }

            if ($purchaseOrder && (int) $purchaseOrder->supplier_id !== (int) $data['supplier_id']) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'أمر الشراء المحدد لا يتبع للمورد المحدد.',
                ]);
            }

            if ($goodsReceipt && (int) $goodsReceipt->supplier_id !== (int) $data['supplier_id']) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'سند الاستلام المحدد لا يتبع للمورد المحدد.',
                ]);
            }

            if ($goodsReceipt && $goodsReceipt->statusValue() !== GoodsReceiptStatus::Posted->value) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'لا يمكن إنشاء فاتورة مورد من سند استلام غير مُرحّل.',
                ]);
            }

            foreach ([$purchaseOrder, $goodsReceipt] as $source) {
                if ($source && (int) $source->location_id !== (int) $data['location_id']) {
                    throw ValidationException::withMessages([
                        'location_id' => 'يجب أن يطابق موقع فاتورة المورد موقع مستند الشراء المرتبط بها.',
                    ]);
                }

                if ($source && (int) $source->currency_id !== (int) $data['currency_id']) {
                    throw ValidationException::withMessages([
                        'currency_id' => 'يجب أن تطابق عملة فاتورة المورد عملة مستند الشراء المرتبط بها.',
                    ]);
                }
            }

            $rate = $this->currencies->resolveRate(
                (int) $data['currency_id'],
                $data['exchange_rate'] ?? null,
                Carbon::parse($data['invoice_date']),
            );

            $items = $data['items'] ?? $this->itemsFromSource($goodsReceipt, $purchaseOrder);

            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'أضف بنداً واحداً على الأقل إلى فاتورة المورد.',
                ]);
            }

            $totals = $this->totals($items, $data);
            $status = $this->initialStatus($data['due_date'] ?? null);

            $invoice = SupplierInvoice::query()->create([
                'invoice_number' => trim((string) $data['invoice_number']),
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $purchaseOrder?->id,
                'goods_receipt_id' => $goodsReceipt?->id,
                'location_id' => $data['location_id'],
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $rate,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'grand_total' => $totals['grand_total'],
                'base_grand_total' => $this->currencies->money(
                    $this->currencies->toBase($totals['grand_total'], $rate)
                ),
                'paid_amount' => 0,
                'credited_amount' => 0,
                'remaining_amount' => $totals['grand_total'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($totals['items'] as $item) {
                $invoice->items()->create($item);
            }

            ActivityLogger::log(
                userId: $actor->id,
                action: 'supplier_invoice.created',
                module: 'procurement',
                recordType: 'supplier_invoices',
                recordId: $invoice->id,
                oldValues: null,
                newValues: $invoice->only(['invoice_number', 'supplier_id', 'status', 'grand_total']),
                metadata: ['items_count' => count($totals['items'])],
            );

            DB::afterCommit(function () use ($invoice): void {
                $this->notifier->send(
                    type: 'supplier_invoice_created',
                    title: 'فاتورة مورد جديدة',
                    message: "تم تسجيل فاتورة المورد رقم {$invoice->invoice_number}.",
                    url: route('supplier-invoices.show', $invoice),
                    priority: 'medium',
                    locationId: $invoice->location_id,
                    permissions: ['supplier_invoices.view', 'supplier_payments.create'],
                    extra: ['reference_type' => 'supplier_invoices', 'reference_id' => $invoice->id],
                );
            });

            return $invoice->load(['items.product', 'supplier', 'location', 'currency', 'purchaseOrder', 'goodsReceipt']);
        });
    }

    public function syncPaymentAmounts(SupplierInvoice $invoice): SupplierInvoice
    {
        $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

        if ($invoice->statusValue() === SupplierInvoiceStatus::Cancelled->value) {
            return $invoice;
        }

        $paid = (float) SupplierPayment::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('status', 'confirmed')
            ->sum('applied_amount');

        $credited = (float) $invoice->purchaseReturns()
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->sum('grand_total');

        $total = (float) $invoice->grand_total;
        $credited = min(max(0, $credited), $total);
        $payableAfterCredits = max(0, $total - $credited);
        $paid = min(max(0, $paid), $payableAfterCredits);
        $remaining = max(0, $payableAfterCredits - $paid);

        $status = $remaining <= 0.004
            ? SupplierInvoiceStatus::Paid
            : (($paid > 0 || $credited > 0)
                ? SupplierInvoiceStatus::PartiallyPaid
                : $this->initialStatus($invoice->due_date?->toDateString()));

        $invoice->update([
            'paid_amount' => $this->currencies->money($paid),
            'credited_amount' => $this->currencies->money($credited),
            'remaining_amount' => $this->currencies->money($remaining),
            'status' => $status,
        ]);

        return $invoice->fresh();
    }

    public function cancel(SupplierInvoice $supplierInvoice, User $actor): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $actor) {
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($supplierInvoice->id);

            if ($invoice->statusValue() === SupplierInvoiceStatus::Cancelled->value) {
                throw ValidationException::withMessages([
                    'status' => 'فاتورة المورد ملغاة مسبقاً.',
                ]);
            }

            $hasPayments = SupplierPayment::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->where('status', 'confirmed')
                ->exists();
            $hasReturns = $invoice->purchaseReturns()
                ->where('status', PurchaseReturnStatus::Posted->value)
                ->exists();

            if ($hasPayments || $hasReturns) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء فاتورة مرتبطة بدفعات أو مرتجعات مُرحّلة. سجّل حركة عكسية موثقة بدلاً من ذلك.',
                ]);
            }

            $oldStatus = $invoice->statusValue();
            $invoice->update([
                'status' => SupplierInvoiceStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'supplier_invoice.cancelled',
                module: 'procurement',
                recordType: 'supplier_invoices',
                recordId: $invoice->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => SupplierInvoiceStatus::Cancelled->value],
                metadata: [],
            );

            return $invoice->fresh();
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsFromSource(?GoodsReceipt $goodsReceipt, ?PurchaseOrder $purchaseOrder): array
    {
        if ($goodsReceipt) {
            return $goodsReceipt->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'description' => $item->product?->name_ar ?? $item->product?->name ?? 'منتج',
                'quantity' => $item->accepted_quantity,
                'unit_price' => $item->unit_cost,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ])->all();
        }

        if ($purchaseOrder) {
            return $purchaseOrder->items()->get()->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->ordered_quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'tax_amount' => $item->tax_amount,
            ])->all();
        }

        return [];
    }

    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $data */
    private function totals(array $items, array $data): array
    {
        $normalisedItems = [];
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($items as $item) {
            $quantity = round((float) ($item['quantity'] ?? 0), 3);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 4);
            $lineDiscount = round((float) ($item['discount_amount'] ?? 0), 2);
            $lineTax = round((float) ($item['tax_amount'] ?? 0), 2);

            if ($quantity <= 0 || $unitPrice < 0 || $lineDiscount < 0 || $lineTax < 0) {
                throw ValidationException::withMessages([
                    'items' => 'بيانات بند فاتورة المورد غير صحيحة.',
                ]);
            }

            $gross = $quantity * $unitPrice;
            $lineTotal = max(0, $gross - $lineDiscount + $lineTax);
            $normalisedItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'] ?? 'بند مورد',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $lineDiscount,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
            ];
            $subtotal += $gross;
            $discount += $lineDiscount;
            $tax += $lineTax;
        }

        $discount += round((float) ($data['discount_amount'] ?? 0), 2);
        $tax += round((float) ($data['tax_amount'] ?? 0), 2);
        $total = max(0, $subtotal - $discount + $tax);

        return [
            'items' => $normalisedItems,
            'subtotal' => $this->currencies->money($subtotal),
            'discount_amount' => $this->currencies->money($discount),
            'tax_amount' => $this->currencies->money($tax),
            'grand_total' => $this->currencies->money($total),
        ];
    }

    private function initialStatus(?string $dueDate): SupplierInvoiceStatus
    {
        return $dueDate && now()->startOfDay()->greaterThan($dueDate)
            ? SupplierInvoiceStatus::Overdue
            : SupplierInvoiceStatus::Unpaid;
    }
}
