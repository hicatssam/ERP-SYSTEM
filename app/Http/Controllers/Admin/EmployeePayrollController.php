<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeePayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PaymentMethod;
use App\Services\EmployeeAdvanceRepaymentService;
use App\Services\EmployeeLedgerService;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeePayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly EmployeeLedgerService $ledger,
        private readonly EmployeeAdvanceRepaymentService $advanceRepayments
    ) {
    }

    public function show(Employee $employee): View
    {
        return view('admin.payroll.employee', [
            'employee' => $employee,
            'compensation' => EmployeeCompensationProfile::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->latest('effective_from')
                ->first(),
            'adjustments' => EmployeePayrollAdjustment::query()
                ->where('employee_id', $employee->id)
                ->latest()
                ->get(),
            'advances' => EmployeeAdvance::query()
                ->where('employee_id', $employee->id)
                ->with([
                    'repayments.paymentMethod',
                    'repayments.verifiedBy.employee',
                ])
                ->latest('issued_at')
                ->latest('id')
                ->get(),
            'ledgerEntries' => $this->ledger->statement(
                $employee,
                50
            ),
            'ledgerBalance' => $this->ledger->balance($employee),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderByDesc('is_base')
                ->orderBy('code')
                ->get(),
            'periods' => PayrollPeriod::query()
                ->latest('start_date')
                ->limit(24)
                ->get(),
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function compensation(
        Request $request,
        Employee $employee
    ): RedirectResponse {
        $data = $request->validate([
            'salary_basis' => [
                'required',
                Rule::in(['monthly', 'daily', 'hourly']),
            ],
            'base_salary' => [
                'required',
                'numeric',
                'min:0',
            ],
            'currency_id' => [
                'required',
                Rule::exists('currencies', 'id'),
            ],
            'payment_method_id' => [
                'nullable',
                'integer',
            ],
            'effective_from' => [
                'required',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $this->payroll->saveCompensation(
            $employee,
            $data,
            $request->user()
        );

        return back()->with(
            'success',
            'تم تحديث إعدادات راتب الموظف.'
        );
    }

    public function adjustment(
        Request $request,
        Employee $employee
    ): RedirectResponse {
        $data = $request->validate([
            'kind' => [
                'required',
                Rule::in([
                    'allowance',
                    'bonus',
                    'deduction',
                ]),
            ],
            'name' => [
                'required',
                'string',
                'max:190',
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'is_recurring' => [
                'nullable',
                'boolean',
            ],
            'payroll_period_id' => [
                'nullable',
                Rule::exists('payroll_periods', 'id'),
            ],
            'effective_from' => [
                'nullable',
                'date',
            ],
            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $data['is_recurring'] =
            $request->boolean('is_recurring');

        $this->payroll->addAdjustment(
            $employee,
            $data,
            $request->user()
        );

        return back()->with(
            'success',
            'تمت إضافة حركة الراتب.'
        );
    }

    public function advance(
        Request $request,
        Employee $employee
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'issued_at' => [
                'required',
                'date',
            ],
            'payment_method_id' => [
                'nullable',
                'integer',
            ],
            'reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $this->payroll->issueAdvance(
            $employee,
            $data,
            $request->user()
        );

        return back()->with(
            'success',
            'تم تسجيل السلفة وإضافتها إلى كشف حساب الموظف.'
        );
    }
    public function repayAdvance(
        Request $request,
        Employee $employee,
        EmployeeAdvance $advance
    ): RedirectResponse {
        abort_unless(
            (int) $advance->employee_id === (int) $employee->id,
            404
        );

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')
                    ->where('is_active', true),
            ],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:10240',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $repayment = $this->advanceRepayments->create(
            $advance,
            $data,
            $request->file('payment_proof'),
            $request->user()
        );

        return back()->with(
            'success',
            $repayment->status === 'pending_verification'
                ? 'تم تسجيل سداد السلفة وهو بانتظار التحقق.'
                : 'تم تسجيل سداد السلفة وتحديث كشف حساب الموظف.'
        );
    }

}
