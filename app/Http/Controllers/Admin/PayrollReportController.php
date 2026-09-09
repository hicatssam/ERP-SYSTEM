<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class PayrollReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $rows = (clone $query)
            ->with([
                'employee',
                'period',
            ])
            ->withSum(
                [
                    'payments as paid_total' => fn ($q) =>
                        $q->where('status', 'posted'),
                ],
                'amount'
            )
            ->latest('payroll_period_id')
            ->paginate(40)
            ->withQueryString();

        $summaryQuery = $this->filteredQuery($request);

        return view('admin.payroll.reports.index', [
            'rows' => $rows,
            'summary' => [
                'employees' => (clone $summaryQuery)
                    ->distinct('employee_id')
                    ->count('employee_id'),
                'gross' => (float) (clone $summaryQuery)
                    ->sum('gross_salary'),
                'deductions' => (float) (clone $summaryQuery)
                    ->sum('deductions_total'),
                'net' => (float) (clone $summaryQuery)
                    ->sum('net_salary'),
                'payable' => (float) (clone $summaryQuery)
                    ->sum('payable_amount'),
            ],
            'periods' => PayrollPeriod::query()
                ->latest('start_date')
                ->get(),
            'employees' => Employee::query()
                ->orderBy('full_name')
                ->get(),
            'locations' => Location::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->filteredQuery($request)
            ->with([
                'employee',
                'period',
            ])
            ->withSum(
                [
                    'payments as paid_total' => fn ($q) =>
                        $q->where('status', 'posted'),
                ],
                'amount'
            )
            ->orderBy('payroll_period_id')
            ->orderBy('employee_id')
            ->get();

        return response()->streamDownload(
            function () use ($rows): void {
                $out = fopen('php://output', 'wb');

                fwrite($out, "\xEF\xBB\xBF");

                fputcsv($out, [
                    'الدورة',
                    'الموظف',
                    'الراتب الأساسي',
                    'البدلات',
                    'المكافآت',
                    'الخصومات',
                    'الإجمالي',
                    'الصافي',
                    'المدفوع',
                    'المتبقي',
                    'الحالة',
                ]);

                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->period?->name,
                        $row->employee?->full_name,
                        $row->base_salary,
                        $row->allowances_total,
                        $row->bonuses_total,
                        $row->deductions_total,
                        $row->gross_salary,
                        $row->net_salary,
                        $row->paid_total ?? 0,
                        $row->payable_amount,
                        $row->status,
                    ]);
                }

                fclose($out);
            },
            'payroll-report-' . now()->format('Ymd-His') . '.csv',
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    private function filteredQuery(
        Request $request
    ): Builder {
        return PayrollItem::query()
            ->when(
                $request->filled('period_id'),
                fn (Builder $q) =>
                    $q->where(
                        'payroll_period_id',
                        $request->integer('period_id')
                    )
            )
            ->when(
                $request->filled('employee_id'),
                fn (Builder $q) =>
                    $q->where(
                        'employee_id',
                        $request->integer('employee_id')
                    )
            )
            ->when(
                $request->filled('status'),
                fn (Builder $q) =>
                    $q->where(
                        'status',
                        $request->string('status')
                    )
            )
            ->when(
                $request->filled('location_id'),
                function (Builder $q) use ($request): void {
                    $locationId =
                        $request->integer('location_id');

                    $q->whereHas(
                        'period',
                        fn (Builder $period) =>
                            $period->where(
                                'location_id',
                                $locationId
                            )
                    );
                }
            );
    }
}
