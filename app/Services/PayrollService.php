<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollItemComponent;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(
        private readonly EmployeeLedgerService $ledger
    ) {
    }

    public function calculatePeriod(
        PayrollPeriod $period,
        User $actor
    ): PayrollPeriod {
        return DB::transaction(function () use ($period, $actor): PayrollPeriod {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! in_array($period->status, ['draft', 'calculated'], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'لا يمكن إعادة احتساب هذه الدورة بعد اعتمادها.',
                ]);
            }
            $employees = Employee::query()
                ->where(function ($query): void {
                    $query
                        ->whereNull('employment_status')
                        ->orWhereNotIn('employment_status', [
                            'inactive',
                            'terminated',
                            'ended',
                            'disabled',
                        ]);
                })
                ->get();

            foreach ($employees as $employee) {
                $this->calculateEmployee($period, $employee);
            }

            $period->update([
                'status' => 'calculated',
            ]);

            return $period->fresh(['items.employee', 'items.components']);
        });
    }

    public function calculateEmployee(
        PayrollPeriod $period,
        Employee $employee
    ): PayrollItem {
        $profile = EmployeeCompensationProfile::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $period->end_date)
            ->where(function ($query) use ($period): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $period->start_date);
            })
            ->latest('effective_from')
            ->first();

        $baseSalary = (float) ($profile?->base_salary ?? 0);

        $adjustments = EmployeePayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where(function ($query) use ($period): void {
                $query
                    ->where('payroll_period_id', $period->id)
                    ->orWhere(function ($inner) use ($period): void {
                        $inner
                            ->whereNull('payroll_period_id')
                            ->where(function ($dateQuery) use ($period): void {
                                $dateQuery
                                    ->whereNull('effective_from')
                                    ->orWhereDate('effective_from', '<=', $period->end_date);
                            })
                            ->where(function ($dateQuery) use ($period): void {
                                $dateQuery
                                    ->whereNull('effective_to')
                                    ->orWhereDate('effective_to', '>=', $period->start_date);
                            });
                    });
            })
            ->get();

        $allowances = (float) $adjustments
            ->where('kind', 'allowance')
            ->sum('amount');

        $bonuses = (float) $adjustments
            ->where('kind', 'bonus')
            ->sum('amount');

        $deductions = (float) $adjustments
            ->where('kind', 'deduction')
            ->sum('amount');

        $gross = $baseSalary + $allowances + $bonuses;
        $net = max(0, $gross - $deductions);

        $existingBalance = $this->ledger->balance($employee);
        $payable = max(0, $existingBalance + $net);

        $item = PayrollItem::query()->updateOrCreate(
            [
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ],
            [
                'compensation_profile_id' => $profile?->id,
                'base_salary' => $baseSalary,
                'allowances_total' => $allowances,
                'bonuses_total' => $bonuses,
                'deductions_total' => $deductions,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'payable_amount' => $payable,
                'status' => 'calculated',
            ]
        );

        $item->components()->delete();

        if ($baseSalary > 0) {
            $item->components()->create([
                'kind' => 'basic_salary',
                'direction' => 'credit',
                'label' => 'الراتب الأساسي',
                'amount' => $baseSalary,
                'source_type' => 'compensation_profile',
                'source_id' => $profile?->id,
            ]);
        }

        foreach ($adjustments as $adjustment) {
            $direction = $adjustment->kind === 'deduction'
                ? 'debit'
                : 'credit';

            $item->components()->create([
                'kind' => $adjustment->kind,
                'direction' => $direction,
                'label' => $adjustment->name,
                'amount' => $adjustment->amount,
                'source_type' => 'employee_payroll_adjustment',
                'source_id' => $adjustment->id,
            ]);
        }

        return $item->fresh(['components', 'employee']);
    }

    public function approvePeriod(
        PayrollPeriod $period,
        User $actor
    ): PayrollPeriod {
        return DB::transaction(function () use ($period, $actor): PayrollPeriod {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($period->status !== 'calculated') {
                throw ValidationException::withMessages([
                    'payroll' => 'يجب احتساب دورة الرواتب قبل اعتمادها.',
                ]);
            }

            $period->loadMissing(['items.components', 'items.period']);

            foreach ($period->items as $item) {
                foreach ($item->components as $component) {
                    $this->ledger->postComponent(
                        $item,
                        $component,
                        $actor->id
                    );
                }

                $item->update([
                    'status' => 'approved',
                    'payable_amount' => max(
                        0,
                        $this->ledger->balance($item->employee_id)
                    ),
                ]);

                $this->recoverAdvancesAgainstApprovedSalary($item);
            }

            $period->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            return $period->fresh(['items.employee', 'items.components']);
        });
    }

    public function recordPayment(
        PayrollItem $item,
        float $amount,
        ?int $paymentMethodId,
        ?string $reference,
        ?string $notes,
        User $actor
    ): PayrollPayment {
        if (! in_array($item->status, ['approved', 'paid'], true)) {
            throw ValidationException::withMessages([
                'amount' => 'لا يمكن صرف راتب قبل اعتماد دورة الرواتب.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'قيمة الدفعة يجب أن تكون أكبر من صفر.',
            ]);
        }

        $available = max(
            0,
            $this->ledger->balance($item->employee_id)
        );

        if ($amount > $available + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => 'قيمة الدفعة أكبر من الرصيد المستحق للموظف.',
            ]);
        }

        return DB::transaction(function () use (
            $item,
            $amount,
            $paymentMethodId,
            $reference,
            $notes,
            $actor
        ): PayrollPayment {
            $payment = PayrollPayment::create([
                'payroll_item_id' => $item->id,
                'employee_id' => $item->employee_id,
                'amount' => $amount,
                'payment_method_id' => $paymentMethodId,
                'paid_at' => now(),
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $this->ledger->postPayment(
                $payment,
                $actor->id
            );

            $remaining = max(
                0,
                $this->ledger->balance($item->employee_id)
            );

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

            return $payment;
        });
    }

    public function saveCompensation(
        Employee $employee,
        array $data,
        User $actor
    ): EmployeeCompensationProfile {
        return DB::transaction(function () use (
            $employee,
            $data,
            $actor
        ): EmployeeCompensationProfile {
            EmployeeCompensationProfile::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'effective_to' => now()
                        ->subDay()
                        ->toDateString(),
                ]);

            return EmployeeCompensationProfile::create([
                ...$data,
                'employee_id' => $employee->id,
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function addAdjustment(
        Employee $employee,
        array $data,
        User $actor
    ): EmployeePayrollAdjustment {
        return EmployeePayrollAdjustment::create([
            ...$data,
            'employee_id' => $employee->id,
            'created_by' => $actor->id,
        ]);
    }

    public function issueAdvance(
        Employee $employee,
        array $data,
        User $actor
    ): EmployeeAdvance {
        return DB::transaction(function () use (
            $employee,
            $data,
            $actor
        ): EmployeeAdvance {
            $advance = EmployeeAdvance::create([
                ...$data,
                'employee_id' => $employee->id,
                'recovered_amount' => 0,
                'outstanding_amount' => $data['amount'],
                'status' => 'open',
                'created_by' => $actor->id,
            ]);

            $currencyId = EmployeeCompensationProfile::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->value('currency_id');

            $this->ledger->postAdvance(
                $employee->id,
                $advance->id,
                (float) $advance->amount,
                $advance->issued_at->toDateString(),
                $currencyId,
                $advance->reference,
                $actor->id
            );

            return $advance;
        });
    }

    private function recoverAdvancesAgainstApprovedSalary(
        PayrollItem $item
    ): void {
        $remainingSalary = (float) $item->net_salary;

        $advances = EmployeeAdvance::query()
            ->where('employee_id', $item->employee_id)
            ->where('status', 'open')
            ->where('outstanding_amount', '>', 0)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($advances as $advance) {
            if ($remainingSalary <= 0) {
                break;
            }

            $recover = min(
                $remainingSalary,
                (float) $advance->outstanding_amount
            );

            $newRecovered =
                (float) $advance->recovered_amount + $recover;

            $newOutstanding =
                max(0, (float) $advance->outstanding_amount - $recover);

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

            $remainingSalary -= $recover;
        }
    }
}
