<?php

namespace App\Services\Procurement;

use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CurrencyConversionService $currencies,
        private readonly SupplierInvoiceService $invoices,
        private readonly ProcurementNotifier $notifier,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function record(array $data, User $actor): SupplierPayment
    {
        return DB::transaction(function () use ($data, $actor) {
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($data['supplier_invoice_id']);

            if (! $invoice->isPayable()) {
                throw ValidationException::withMessages([
                    'supplier_invoice_id' => 'هذه الفاتورة غير متاحة لتسجيل دفعة.',
                ]);
            }

            if ((int) $invoice->supplier_id !== (int) $data['supplier_id']) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'المورد المحدد لا يطابق مورد الفاتورة.',
                ]);
            }

            if (isset($data['location_id']) && (int) $data['location_id'] !== (int) $invoice->location_id) {
                throw ValidationException::withMessages([
                    'location_id' => 'يجب أن يتم تسجيل الدفعة على موقع فاتورة المورد نفسها.',
                ]);
            }

            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'قيمة الدفعة يجب أن تكون أكبر من صفر.',
                ]);
            }

            $paymentRate = $this->currencies->resolveRate(
                (int) $data['currency_id'],
                $data['exchange_rate'] ?? null,
                Carbon::parse($data['payment_date']),
            );
            $baseAmount = (float) $this->currencies->toBase($amount, $paymentRate);
            $appliedAmount = (float) $this->currencies->fromBase($baseAmount, (string) $invoice->exchange_rate);

            if ($appliedAmount > (float) $invoice->remaining_amount + 0.004) {
                throw ValidationException::withMessages([
                    'amount' => 'الدفعة أكبر من الرصيد المستحق في فاتورة المورد.',
                ]);
            }

            $payment = SupplierPayment::query()->create([
                'payment_number' => $this->numbers->next('supplier_payment', 'SPY'),
                'supplier_id' => $invoice->supplier_id,
                'supplier_invoice_id' => $invoice->id,
                'location_id' => $data['location_id'] ?? $invoice->location_id,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $paymentRate,
                'amount' => $this->currencies->money($amount),
                'base_amount' => $this->currencies->money($baseAmount),
                'applied_amount' => $this->currencies->money($appliedAmount),
                'payment_date' => $data['payment_date'],
                'payment_method_id' => $data['payment_method_id'],
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'confirmed',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $invoice = $this->invoices->syncPaymentAmounts($invoice);

            ActivityLogger::log(
                userId: $actor->id,
                action: 'supplier_payment.recorded',
                module: 'procurement',
                recordType: 'supplier_payments',
                recordId: $payment->id,
                oldValues: null,
                newValues: $payment->only(['payment_number', 'supplier_id', 'supplier_invoice_id', 'amount']),
                metadata: ['invoice_remaining_amount' => $invoice->remaining_amount],
            );

            DB::afterCommit(function () use ($payment, $invoice): void {
                $this->notifier->send(
                    type: 'supplier_payment_recorded',
                    title: 'تم تسجيل دفعة للمورد',
                    message: "تم تسجيل الدفعة {$payment->payment_number} لفاتورة المورد {$invoice->invoice_number}.",
                    url: route('supplier-invoices.show', $invoice),
                    priority: 'medium',
                    locationId: $invoice->location_id,
                    permissions: ['supplier_payments.view', 'supplier_invoices.view'],
                    extra: ['reference_type' => 'supplier_payments', 'reference_id' => $payment->id],
                );
            });

            return $payment->load(['supplier', 'supplierInvoice', 'currency', 'paymentMethod', 'creator']);
        });
    }
}
