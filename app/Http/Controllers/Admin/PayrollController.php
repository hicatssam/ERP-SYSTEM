<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\AttendanceCorrectionRequest;
use App\Models\EmployeeSelfAttendanceRequest;
use App\Models\EmployeeLeaveRequest;
use App\Models\PaymentMethod;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use App\Services\PayrollAccess;
use App\Services\PayrollSettlementService;
use App\Services\Procurement\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly PayrollSettlementService $settlements,
        private readonly DocumentNumberService $numbers,
        private readonly PayrollAccess $access,
    ) {
    }

    public function index(Request $request): View
    {
        return view('admin.payroll.index', [
            'periods' => $this->access->periods($request->user())
                ->latest('start_date')
                ->latest('id')
                ->paginate(20),
            'baseCurrency' => Currency::query()
                ->where('is_base', true)
                ->first(),
            'canManageGlobal' => $this->access->global($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->access->assertGlobalWrite($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $period = DB::transaction(function () use ($data, $request): PayrollPeriod {
            $overlap = PayrollPeriod::query()
                ->whereDate('start_date', '<=', $data['end_date'])
                ->whereDate('end_date', '>=', $data['start_date'])
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_date' => 'تتداخل هذه الدورة مع دورة رواتب موجودة.',
                ]);
            }

            $baseCurrencyId = Currency::query()->where('is_base', true)->value('id');
            if (! $baseCurrencyId) {
                throw ValidationException::withMessages([
                    'currency' => 'يجب تحديد عملة أساسية قبل إنشاء دورة الرواتب.',
                ]);
            }

            return PayrollPeriod::create([
                ...$data,
                'code' => $this->numbers->next('payroll_period', 'PAY'),
                'currency_id' => $baseCurrencyId,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('payroll.show', $period)
            ->with('success', 'تم إنشاء دورة الرواتب.');
    }

    public function show(Request $request, PayrollPeriod $period): View
    {
        $this->access->assertPeriod($request->user(), $period);
        $period->setRelation('items', $this->access->items($request->user())
            ->where('payroll_period_id', $period->id)
            ->with(['employee', 'components', 'payments.paymentMethod', 'payments.employee'])
            ->get());
        $ids = $period->items->pluck('employee_id')->all();
        $pendingLeaves = EmployeeLeaveRequest::query()->whereIn('employee_id', $ids)
            ->where('status', 'pending')
            ->whereDate('start_date', '<=', $period->end_date->toDateString())
            ->whereDate('end_date', '>=', $period->start_date->toDateString())->count();
        $pendingCorrections = AttendanceCorrectionRequest::query()->whereIn('employee_id', $ids)
            ->where('status', 'pending')
            ->whereDate('work_date', '>=', $period->start_date->toDateString())
            ->whereDate('work_date', '<=', $period->end_date->toDateString())->count();
        $pendingSelfAttendance = EmployeeSelfAttendanceRequest::query()->whereIn('employee_id', $ids)
            ->where('status', 'pending')
            ->whereDate('work_date', '>=', $period->start_date->toDateString())
            ->whereDate('work_date', '<=', $period->end_date->toDateString())->count();

        return view('admin.payroll.show', [
            'period' => $period,
            'canManageGlobal' => $this->access->global($request->user()),
            'pendingLeaves' => $pendingLeaves,
            'pendingCorrections' => $pendingCorrections,
            'pendingSelfAttendance' => $pendingSelfAttendance,
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function calculate(
        Request $request,
        PayrollPeriod $period
    ): RedirectResponse {
        $this->access->assertGlobalWrite($request->user());
        $this->payroll->calculatePeriod(
            $period,
            $request->user()
        );

        return back()->with('success', 'تم احتساب دورة الرواتب.');
    }

    public function approve(
        Request $request,
        PayrollPeriod $period
    ): RedirectResponse {
        $this->access->assertGlobalWrite($request->user());
        $this->payroll->approvePeriod(
            $period,
            $request->user()
        );

        return back()->with(
            'success',
            'تم اعتماد دورة الرواتب وترحيل الاستحقاقات إلى كشوف الموظفين.'
        );
    }

    public function pay(
        Request $request,
        PayrollItem $item
    ): RedirectResponse {
        $this->access->assertItem($request->user(), $item);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id'),
            ],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:10240',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->settlements->createPayment(
            $item,
            $data,
            $request->file('payment_proof'),
            $request->user()
        );

        $message = $payment->status === 'pending_verification'
            ? 'تم تسجيل الدفعة وهي بانتظار التحقق.'
            : 'تم صرف الراتب وترحيل الدفعة إلى كشف حساب الموظف.';

        return back()->with('success', $message);
    }
}
