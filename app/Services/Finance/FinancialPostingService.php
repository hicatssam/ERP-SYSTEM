<?php

namespace App\Services\Finance;

use App\Enums\LedgerEntryType;
use App\Models\FinancialPeriod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class FinancialPostingService
{
    public function __construct(private readonly FinancialPeriodResolver $periods) {}

    public function sale(Invoice $invoice, User $actor): void
    {
        $this->post(
            key: "invoice:{$invoice->id}:sale",
            type: LedgerEntryType::Sale,
            amount: (float) $invoice->total_amount,
            date: $invoice->issued_at ?? now(),
            locationId: (int) $invoice->location_id,
            actorId: $actor->id,
            referenceType: 'invoices',
            referenceId: $invoice->id,
            invoiceId: $invoice->id,
            description: "فاتورة {$invoice->invoice_number}",
        );
    }

    public function collection(Payment $payment, User $actor, ?string $keySuffix = null, ?float $amount = null): void
    {
        $this->post(
            key: "payment:{$payment->id}:collection".($keySuffix ? ":{$keySuffix}" : ''),
            type: LedgerEntryType::PaymentCollection,
            amount: $amount ?? (float) $payment->amount,
            date: $payment->paid_at ?? now(),
            locationId: (int) $payment->location_id,
            actorId: $actor->id,
            referenceType: 'payments',
            referenceId: $payment->id,
            orderId: $payment->orderTypeValue() === 'order' ? (int) $payment->order_id : null,
            specialCakeOrderId: $payment->orderTypeValue() === 'special_cake_order' ? (int) $payment->order_id : null,
            description: "تحصيل دفعة #{$payment->id}",
        );
    }

    public function paymentReversal(Payment $payment, User $actor, string $keySuffix, float $amount): void
    {
        $this->post(
            key: "payment:{$payment->id}:reversal:{$keySuffix}",
            type: LedgerEntryType::PaymentReversal,
            amount: $amount,
            date: now(),
            locationId: (int) $payment->location_id,
            actorId: $actor->id,
            referenceType: 'payments',
            referenceId: $payment->id,
            description: "عكس/تصحيح دفعة #{$payment->id}",
        );
    }

    public function refund(Refund $refund, Payment $payment, User $actor): void
    {
        $this->post(
            key: "refund:{$refund->id}",
            type: LedgerEntryType::Refund,
            amount: (float) $refund->amount,
            date: $refund->processed_at ?? now(),
            locationId: (int) $payment->location_id,
            actorId: $actor->id,
            referenceType: 'refunds',
            referenceId: $refund->id,
            orderId: $payment->orderTypeValue() === 'order' ? (int) $payment->order_id : null,
            specialCakeOrderId: $payment->orderTypeValue() === 'special_cake_order' ? (int) $payment->order_id : null,
            description: "استرداد دفعة #{$payment->id}",
        );
    }

    public function saleCancellation(Invoice $invoice, User $actor): void
    {
        $this->post(
            key: "invoice:{$invoice->id}:cancel",
            type: LedgerEntryType::SaleCancellation,
            amount: (float) $invoice->total_amount,
            date: now(),
            locationId: (int) $invoice->location_id,
            actorId: $actor->id,
            referenceType: 'invoices',
            referenceId: $invoice->id,
            invoiceId: $invoice->id,
            description: "إلغاء فاتورة {$invoice->invoice_number}",
        );
    }

    private function post(
        string $key,
        LedgerEntryType $type,
        float $amount,
        CarbonInterface|string $date,
        int $locationId,
        int $actorId,
        string $referenceType,
        int $referenceId,
        ?int $invoiceId = null,
        ?int $orderId = null,
        ?int $specialCakeOrderId = null,
        ?string $description = null,
    ): void {
        if (! Schema::hasTable('sales_ledger_entries') || $amount <= 0) {
            return;
        }

        $periodId = null;
        if (FinancialPeriod::query()->exists()) {
            $periodId = $this->periods->openForDate($date, true)->id;
        }

        SalesLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'location_id' => $locationId,
                'financial_period_id' => $periodId,
                'entry_date' => $date instanceof CarbonInterface ? $date->toDateString() : $date,
                'entry_type' => $type,
                'amount' => round($amount, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'invoice_id' => $invoiceId,
                'order_id' => $orderId,
                'special_cake_order_id' => $specialCakeOrderId,
                'description' => $description,
                'currency_code' => 'ILS',
                'created_by' => $actorId,
                'created_at' => now(),
            ]
        );
    }
}
