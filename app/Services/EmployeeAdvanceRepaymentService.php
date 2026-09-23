<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceRepayment;
use App\Models\EmployeeLedgerEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeAdvanceRepaymentService
{
    public function __construct(
        private readonly EmployeeLedgerService $ledger
    ) {
    }

    public function create(
        EmployeeAdvance $advance,
        array $data,
        ?UploadedFile $proof,
        User $actor
    ): EmployeeAdvanceRepayment {
        $amount = round((float) ($data['amount'] ?? 0), 4);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'قيمة السداد يجب أن تكون أكبر من صفر.',
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
                'payment_proof' => 'يرجى إرفاق إثبات السداد لهذه الطريقة.',
            ]);
        }

        $paidAt = Carbon::parse($data['paid_at'] ?? now());

        return DB::transaction(function () use (
            $advance,
            $data,
            $proof,
            $actor,
            $method,
            $amount,
            $paidAt
        ): EmployeeAdvanceRepayment {
            $advance = EmployeeAdvance::query()
                ->lockForUpdate()
                ->findOrFail($advance->id);

            if (
                ! in_array($advance->status, ['open', 'partial'], true)
                || (float) $advance->outstanding_amount <= 0.0001
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'هذه السلفة مسددة بالكامل ولا تقبل دفعات جديدة.',
                ]);
            }

            $pendingReserved = (float) EmployeeAdvanceRepayment::query()
                ->where('employee_advance_id', $advance->id)
                ->where('status', 'pending_verification')
                ->sum('amount');

            $available = max(
                0,
                (float) $advance->outstanding_amount - $pendingReserved
            );

            if ($amount > $available + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'قيمة السداد أكبر من المتبقي المتاح على السلفة.',
                ]);
            }

            $currencyId = EmployeeLedgerEntry::query()
                ->where('source_type', 'employee_advance')
                ->where('source_id', $advance->id)
                ->value('currency_id');

            $status = (bool) $method->requires_verification
                ? 'pending_verification'
                : 'posted';

            $proofPath = $proof
                ? $proof->store('payroll/advance-repayment-proofs', 'public')
                : null;

            $repayment = EmployeeAdvanceRepayment::query()->create([
                'document_number' => $this->nextDocumentNumber(),
                'employee_advance_id' => $advance->id,
                'employee_id' => $advance->employee_id,
                'amount' => $amount,
                'currency_id' => $currencyId,
                'payment_method_id' => $method->id,
                'status' => $status,
                'paid_at' => $paidAt,
                'reference' => $data['reference'] ?? null,
                'payment_proof' => $proofPath,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($status === 'posted') {
                $this->applyRepayment($repayment, $advance, $actor);
            }

            return $repayment->fresh([
                'advance',
                'employee',
                'paymentMethod',
                'currency',
            ]);
        });
    }

    public function verify(
        EmployeeAdvanceRepayment $repayment,
        User $actor
    ): EmployeeAdvanceRepayment {
        return DB::transaction(function () use (
            $repayment,
            $actor
        ): EmployeeAdvanceRepayment {
            $repayment = EmployeeAdvanceRepayment::query()
                ->lockForUpdate()
                ->findOrFail($repayment->id);

            if ($repayment->status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'repayment' => 'هذه الدفعة ليست بانتظار التحقق.',
                ]);
            }

            $advance = EmployeeAdvance::query()
                ->lockForUpdate()
                ->findOrFail($repayment->employee_advance_id);

            if (
                (float) $repayment->amount
                > (float) $advance->outstanding_amount + 0.0001
            ) {
                throw ValidationException::withMessages([
                    'repayment' => 'قيمة السداد أصبحت أكبر من المتبقي الحالي على السلفة.',
                ]);
            }

            $repayment->update([
                'status' => 'posted',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->applyRepayment(
                $repayment->fresh(),
                $advance,
                $actor
            );

            return $repayment->fresh([
                'advance',
                'employee',
                'paymentMethod',
            ]);
        });
    }

    public function reject(
        EmployeeAdvanceRepayment $repayment,
        string $reason,
        User $actor
    ): EmployeeAdvanceRepayment {
        return DB::transaction(function () use (
            $repayment,
            $reason,
            $actor
        ): EmployeeAdvanceRepayment {
            $repayment = EmployeeAdvanceRepayment::query()
                ->lockForUpdate()
                ->findOrFail($repayment->id);

            if ($repayment->status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'repayment' => 'هذه الدفعة ليست بانتظار التحقق.',
                ]);
            }

            $repayment->update([
                'status' => 'rejected',
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            return $repayment->fresh();
        });
    }

    private function applyRepayment(
        EmployeeAdvanceRepayment $repayment,
        EmployeeAdvance $advance,
        User $actor
    ): void {
        $newRecovered = round(
            (float) $advance->recovered_amount
            + (float) $repayment->amount,
            4
        );

        $newOutstanding = max(
            0,
            round(
                (float) $advance->outstanding_amount
                - (float) $repayment->amount,
                4
            )
        );

        $advance->update([
            'recovered_amount' => $newRecovered,
            'outstanding_amount' => $newOutstanding,
            'status' => $newOutstanding <= 0.0001
                ? 'settled'
                : 'open',
            'settled_at' => $newOutstanding <= 0.0001
                ? now()
                : null,
        ]);

        $this->ledger->postAdvanceRepayment(
            $repayment,
            $actor->id
        );
    }

    private function nextDocumentNumber(): string
    {
        do {
            $number = 'ADV-RPY-'
                . now()->format('Ymd-His')
                . '-'
                . Str::upper(Str::random(5));
        } while (
            EmployeeAdvanceRepayment::query()
                ->where('document_number', $number)
                ->exists()
        );

        return $number;
    }
}
