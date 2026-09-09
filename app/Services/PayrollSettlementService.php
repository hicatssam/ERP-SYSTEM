<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\EmployeeLedgerEntry;
use App\Models\FinancialPeriod;
use App\Models\PaymentMethod;
use App\Models\PayrollItem;
use App\Models\PayrollPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayrollSettlementService
{
    public function __construct(
        private readonly EmployeeLedgerService $ledger
    ) {
    }

    public function createPayment(
        PayrollItem $item,
        array $data,
        ?UploadedFile $proof,
        User $actor
    ): PayrollPayment {
        $item->loadMissing(['period', 'employee']);
        $amount = round((float) $data['amount'], 4);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'قيمة الدفعة يجب أن تكون أكبر من صفر.',
            ]);
        }

        $method = PaymentMethod::query()
            ->whereKey($data['payment_method_id'])
            ->where('is_active', true)
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'طريقة الدفع غير متاحة.',
            ]);
        }

        if (
            (bool) $method->requires_reference
            && blank($data['reference'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'reference' => 'رقم المرجع مطلوب لطريقة الدفع المحددة.',
            ]);
        }

        if (
            (bool) $method->requires_verification
            && ! $proof
        ) {
            throw ValidationException::withMessages([
                'payment_proof' => 'يرجى إرفاق إثبات الدفع لهذه الطريقة.',
            ]);
        }

        $paidAt = Carbon::parse(
            $data['paid_at'] ?? now()
        );

        $financialPeriod = $this->resolveOpenFinancialPeriod(
            $paidAt
        );

        $locationId = $this->resolveLocationId(
            $item,
            $paidAt
        );

        $currencyId = $item->period->currency_id
            ?: Currency::query()
                ->where('is_base', true)
                ->value('id');

        [$exchangeRate, $baseAmount] =
            $this->resolveBaseAmount(
                $currencyId,
                $amount,
                $paidAt
            );

        $status = (bool) $method->requires_verification
            ? 'pending_verification'
            : 'posted';

        return DB::transaction(function () use (
            $item,
            $amount,
            $method,
            $financialPeriod,
            $locationId,
            $currencyId,
            $exchangeRate,
            $baseAmount,
            $paidAt,
            $proof,
            $status,
            $data,
            $actor
        ): PayrollPayment {
            $item = PayrollItem::query()
                ->with(['period', 'employee'])
                ->lockForUpdate()
                ->findOrFail($item->id);

            $financialPeriod = FinancialPeriod::query()
                ->lockForUpdate()
                ->findOrFail($financialPeriod->id);
            if (! $financialPeriod->isOpen()) {
                throw ValidationException::withMessages([
                    'paid_at' => 'تم إغلاق الفترة المالية قبل حفظ الدفعة.',
                ]);
            }

            if (! in_array($item->status, ['approved', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'amount' => 'لا يمكن صرف راتب قبل اعتماد دورة الرواتب.',
                ]);
            }

            $available = max(0, min(
                (float) $item->payable_amount,
                $this->ledger->balance($item->employee_id)
            ));
            if ($amount > $available + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'قيمة الدفعة أكبر من المبلغ المستحق لهذا الموظف.',
                ]);
            }

            $proofPath = $proof
                ? $proof->store('payroll/payment-proofs', 'public')
                : null;

            $payment = PayrollPayment::create([
                'document_number' =>
                    $this->nextDocumentNumber(),
                'payroll_item_id' => $item->id,
                'employee_id' => $item->employee_id,
                'financial_period_id' =>
                    $financialPeriod->id,
                'location_id' => $locationId,
                'currency_id' => $currencyId,
                'amount' => $amount,
                'exchange_rate' => $exchangeRate,
                'base_amount' => $baseAmount,
                'status' => $status,
                'payment_method_id' => $method->id,
                'paid_at' => $paidAt,
                'reference' => $data['reference'] ?? null,
                'payment_proof' => $proofPath,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($status === 'posted') {
                $this->postPaymentToLedger(
                    $payment,
                    $actor
                );
            }

            return $payment->fresh([
                'employee',
                'item.period',
                'paymentMethod',
                'financialPeriod',
                'currency',
                'location',
            ]);
        });
    }

    public function verify(
        PayrollPayment $payment,
        User $actor
    ): PayrollPayment {
        return DB::transaction(function () use (
            $payment,
            $actor
        ): PayrollPayment {
            $payment = PayrollPayment::query()
                ->with('item.period')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'payment' => 'هذه الدفعة ليست بانتظار التحقق.',
                ]);
            }

            PayrollItem::query()->whereKey($payment->payroll_item_id)->lockForUpdate()->firstOrFail();
            $payment->update([
                'status' => 'posted',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->postPaymentToLedger(
                $payment,
                $actor
            );

            return $payment->fresh();
        });
    }

    public function reject(
        PayrollPayment $payment,
        string $reason,
        User $actor
    ): PayrollPayment {
        return DB::transaction(function () use ($payment, $reason, $actor): PayrollPayment {
            $payment = PayrollPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'payment' => 'هذه الدفعة ليست بانتظار التحقق.',
                ]);
            }

            $payment->update([
                'status' => 'rejected',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $payment->fresh();
        });
    }

    public function void(
        PayrollPayment $payment,
        string $reason,
        User $actor
    ): PayrollPayment {
        return DB::transaction(function () use (
            $payment,
            $reason,
            $actor
        ): PayrollPayment {
            $payment = PayrollPayment::query()
                ->with('item.period')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status !== 'posted') {
                throw ValidationException::withMessages([
                    'payment' => 'يمكن إلغاء الدفعات المرحلة فقط.',
                ]);
            }

            $item = PayrollItem::query()->lockForUpdate()->findOrFail($payment->payroll_item_id);

            $existingReversal = EmployeeLedgerEntry::query()
                ->where(
                    'source_type',
                    'payroll_payment_void'
                )
                ->where('source_id', $payment->id)
                ->exists();

            if (! $existingReversal) {
                EmployeeLedgerEntry::create([
                    'employee_id' => $payment->employee_id,
                    'entry_date' => now()->toDateString(),
                    'direction' => 'credit',
                    'entry_type' => 'salary_payment_void',
                    'amount' => $payment->amount,
                    'currency_id' => $payment->currency_id,
                    'payroll_period_id' =>
                        $payment->item->payroll_period_id,
                    'payroll_item_id' =>
                        $payment->payroll_item_id,
                    'source_type' =>
                        'payroll_payment_void',
                    'source_id' => $payment->id,
                    'description' =>
                        'عكس دفعة راتب '
                        . $payment->document_number,
                    'reference' => $payment->reference,
                    'metadata' => [
                        'reason' => $reason,
                    ],
                    'created_by' => $actor->id,
                ]);
            }

            $payment->update([
                'status' => 'voided',
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $item->update([
                'payable_amount' => max(0, $this->ledger->balance($item->employee_id)),
                'status' => 'approved',
            ]);

            if ($item->period->status === 'paid') {
                $item->period->update([
                    'status' => 'approved',
                    'paid_at' => null,
                ]);
            }

            return $payment->fresh();
        });
    }

    private function postPaymentToLedger(
        PayrollPayment $payment,
        User $actor
    ): void {
        $payment->loadMissing([
            'item.period',
        ]);

        $this->ledger->postPayment(
            $payment,
            $actor->id
        );

        $item = PayrollItem::query()->lockForUpdate()->findOrFail($payment->payroll_item_id);
        $remaining = max(0, $this->ledger->balance($item->employee_id));

        $item->update([
            'payable_amount' => $remaining,
            'status' => $remaining <= 0.0001
                ? 'paid'
                : 'approved',
        ]);

        $period = $item->period;

        if (
            $period->items()
                ->where('status', '!=', 'paid')
                ->doesntExist()
        ) {
            $period->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }
    }

    private function resolveOpenFinancialPeriod(
        Carbon $date
    ): FinancialPeriod {
        $period = FinancialPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'paid_at' =>
                    'لا توجد فترة مالية تغطي تاريخ الصرف.',
            ]);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'paid_at' =>
                    'الفترة المالية الخاصة بتاريخ الصرف غير مفتوحة.',
            ]);
        }

        return $period;
    }

    private function resolveLocationId(
        PayrollItem $item,
        Carbon $date
    ): ?int {
        if ($item->period->location_id) {
            return (int) $item->period->location_id;
        }

        $locationId = DB::table('employee_locations')
            ->where('employee_id', $item->employee_id)
            ->where('is_primary', true)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', $date);
            })
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $date);
            })
            ->latest('started_at')
            ->value('location_id');

        if ($locationId) {
            return (int) $locationId;
        }

        return DB::table('employee_locations')
            ->where('employee_id', $item->employee_id)
            ->where('is_primary', true)
            ->value('location_id');
    }

    private function resolveBaseAmount(
        ?int $currencyId,
        float $amount,
        Carbon $date
    ): array {
        $currency = Currency::query()
            ->find($currencyId);

        if (! $currency || $currency->is_base) {
            return [1.0, round($amount, 4)];
        }

        $rate = DB::table('exchange_rates')
            ->where('currency_id', $currencyId)
            ->whereDate(
                'effective_date',
                '<=',
                $date->toDateString()
            )
            ->orderByDesc('effective_date')
            ->value('rate_to_base');

        if (! $rate) {
            throw ValidationException::withMessages([
                'amount' =>
                    'لا يوجد سعر صرف صالح للعملة في تاريخ الدفع.',
            ]);
        }

        $rate = (float) $rate;

        return [
            $rate,
            round($amount * $rate, 4),
        ];
    }

    private function nextDocumentNumber(): string
    {
        do {
            $number = 'PAY-'
                . now()->format('Ymd-His')
                . '-'
                . Str::upper(Str::random(5));
        } while (
            PayrollPayment::query()
                ->where(
                    'document_number',
                    $number
                )
                ->exists()
        );

        return $number;
    }
}
