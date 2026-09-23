<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvanceRepayment;
use App\Models\EmployeeLedgerEntry;
use App\Models\PayrollItem;
use App\Models\PayrollItemComponent;
use App\Models\PayrollPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeLedgerService
{
    public function balance(Employee|int $employee): float
    {
        $employeeId = $employee instanceof Employee
            ? $employee->id
            : $employee;

        $credit = (float) EmployeeLedgerEntry::query()
            ->where('employee_id', $employeeId)
            ->where('direction', 'credit')
            ->sum('amount');

        $debit = (float) EmployeeLedgerEntry::query()
            ->where('employee_id', $employeeId)
            ->where('direction', 'debit')
            ->sum('amount');

        return round($credit - $debit, 4);
    }

    public function statement(
        Employee $employee,
        int $perPage = 50
    ): LengthAwarePaginator {
        return EmployeeLedgerEntry::query()
            ->where('employee_id', $employee->id)
            ->latest('entry_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function postComponent(
        PayrollItem $item,
        PayrollItemComponent $component,
        ?int $actorId = null
    ): EmployeeLedgerEntry {
        $existing = EmployeeLedgerEntry::query()
            ->where('source_type', 'payroll_item_component')
            ->where('source_id', $component->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return EmployeeLedgerEntry::create([
            'employee_id' => $item->employee_id,
            'entry_date' => $item->period->end_date,
            'direction' => $component->direction,
            'entry_type' => $component->kind,
            'amount' => $component->amount,
            'currency_id' => $item->period->currency_id,
            'payroll_period_id' => $item->payroll_period_id,
            'payroll_item_id' => $item->id,
            'source_type' => 'payroll_item_component',
            'source_id' => $component->id,
            'description' => $component->label,
            'created_by' => $actorId,
        ]);
    }

    public function postPayment(
        PayrollPayment $payment,
        ?int $actorId = null
    ): EmployeeLedgerEntry {
        $existing = EmployeeLedgerEntry::query()
            ->where('source_type', 'payroll_payment')
            ->where('source_id', $payment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $payment->loadMissing('item.period');

        return EmployeeLedgerEntry::create([
            'employee_id' => $payment->employee_id,
            'entry_date' => $payment->paid_at->toDateString(),
            'direction' => 'debit',
            'entry_type' => 'salary_payment',
            'amount' => $payment->amount,
            'currency_id' => $payment->item->period->currency_id,
            'payroll_period_id' => $payment->item->payroll_period_id,
            'payroll_item_id' => $payment->payroll_item_id,
            'source_type' => 'payroll_payment',
            'source_id' => $payment->id,
            'description' => 'دفعة راتب',
            'reference' => $payment->reference,
            'created_by' => $actorId,
        ]);
    }

    public function postAdvance(
        int $employeeId,
        int $advanceId,
        float $amount,
        string $entryDate,
        ?int $currencyId,
        ?string $reference,
        ?int $actorId = null
    ): EmployeeLedgerEntry {
        $existing = EmployeeLedgerEntry::query()
            ->where('source_type', 'employee_advance')
            ->where('source_id', $advanceId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return EmployeeLedgerEntry::create([
            'employee_id' => $employeeId,
            'entry_date' => $entryDate,
            'direction' => 'debit',
            'entry_type' => 'advance',
            'amount' => $amount,
            'currency_id' => $currencyId,
            'source_type' => 'employee_advance',
            'source_id' => $advanceId,
            'description' => 'سلفة موظف',
            'reference' => $reference,
            'created_by' => $actorId,
        ]);
    }
    public function postAdvanceRepayment(
        EmployeeAdvanceRepayment $repayment,
        ?int $actorId = null
    ): EmployeeLedgerEntry {
        $existing = EmployeeLedgerEntry::query()
            ->where('source_type', 'employee_advance_repayment')
            ->where('source_id', $repayment->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return EmployeeLedgerEntry::create([
            'employee_id' => $repayment->employee_id,
            'entry_date' => $repayment->paid_at->toDateString(),
            'direction' => 'credit',
            'entry_type' => 'advance_repayment',
            'amount' => $repayment->amount,
            'currency_id' => $repayment->currency_id,
            'source_type' => 'employee_advance_repayment',
            'source_id' => $repayment->id,
            'description' => 'سداد سلفة ' . $repayment->document_number,
            'reference' => $repayment->reference,
            'metadata' => [
                'employee_advance_id' => $repayment->employee_advance_id,
                'payment_method_id' => $repayment->payment_method_id,
            ],
            'created_by' => $actorId,
        ]);
    }

}
