<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLedgerEntry;
use App\Models\PayrollItem;
use App\Models\PayrollPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollDocumentController extends Controller
{
    public function payslip(PayrollItem $item): View
    {
        $item->load([
            'employee',
            'period',
            'components',
            'payments' => fn ($q) => $q
                ->where('status', 'posted')
                ->with('paymentMethod'),
        ]);

        return view('admin.payroll.payslip', [
            'item' => $item,
            'paidTotal' => (float) $item->payments->sum('amount'),
        ]);
    }

    public function paymentReceipt(
        PayrollPayment $payment
    ): View {
        $payment->load([
            'employee',
            'item.period',
            'paymentMethod',
            'financialPeriod',
            'currency',
            'location',
            'creator',
            'verifier',
        ]);

        return view('admin.payroll.payment-receipt', [
            'payment' => $payment,
        ]);
    }

    public function employeeStatement(
        Request $request,
        Employee $employee
    ): View {
        $from = $request->date('from');
        $to = $request->date('to');

        $query = EmployeeLedgerEntry::query()
            ->where('employee_id', $employee->id);

        if ($from) {
            $query->whereDate(
                'entry_date',
                '>=',
                $from->toDateString()
            );
        }

        if ($to) {
            $query->whereDate(
                'entry_date',
                '<=',
                $to->toDateString()
            );
        }

        $entries = $query
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $opening = 0.0;

        if ($from) {
            $openingCredit = (float) EmployeeLedgerEntry::query()
                ->where('employee_id', $employee->id)
                ->whereDate(
                    'entry_date',
                    '<',
                    $from->toDateString()
                )
                ->where('direction', 'credit')
                ->sum('amount');

            $openingDebit = (float) EmployeeLedgerEntry::query()
                ->where('employee_id', $employee->id)
                ->whereDate(
                    'entry_date',
                    '<',
                    $from->toDateString()
                )
                ->where('direction', 'debit')
                ->sum('amount');

            $opening = $openingCredit - $openingDebit;
        }

        $running = $opening;

        $rows = $entries->map(function ($entry) use (&$running): array {
            $running += $entry->direction === 'credit'
                ? (float) $entry->amount
                : -1 * (float) $entry->amount;

            return [
                'entry' => $entry,
                'balance' => $running,
            ];
        });

        return view('admin.payroll.statement-print', [
            'employee' => $employee,
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'rows' => $rows,
            'closing' => $running,
        ]);
    }
}
