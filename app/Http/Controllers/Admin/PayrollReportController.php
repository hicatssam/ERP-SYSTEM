<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrDepartment;
use App\Models\HrCostCenter;
use App\Models\Location;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\PayrollAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class PayrollReportController extends Controller
{
    public function __construct(private readonly PayrollAccess $access)
    {
    }

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $rows = (clone $query)
            ->with([
                'employee',
                'period',
                'orgAssignment.department',
                'orgAssignment.costCenter',
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
        $grouped = DB::query()->fromSub(
            $this->filteredQuery($request)->select([
                'payroll_items.employee_id', 'payroll_items.net_salary',
                'payroll_items.org_assignment_id',
            ]), 'scoped'
        )->leftJoin('employee_org_assignments as org', 'org.id', '=', 'scoped.org_assignment_id')
            ->selectRaw('org.cost_center_id, COUNT(DISTINCT scoped.employee_id) as employees, COALESCE(SUM(scoped.net_salary), 0) as net')
            ->groupBy('org.cost_center_id')->orderByDesc('net')->get();
        $centerNames = HrCostCenter::query()->whereIn('id', $grouped->pluck('cost_center_id')->filter())
            ->pluck('name', 'id');

        return view('admin.payroll.reports.index', [
            'rows' => $rows,
            'costCenterSummary' => $grouped,
            'costCenterNames' => $centerNames,
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
            'periods' => $this->access->periods($request->user())
                ->latest('start_date')
                ->get(),
            'employees' => $this->access->employees($request->user())
                ->orderBy('full_name')
                ->get(),
            'locations' => Location::query()
                ->when(! $this->access->global($request->user()),
                    fn (Builder $q) => $q->whereKey($request->user()->primaryLocation()?->id ?? 0))
                ->orderBy('name')
                ->get(),
            'departments' => HrDepartment::query()
                ->when(! $this->access->global($request->user()), fn (Builder $query) => $query
                    ->where(fn (Builder $scope) => $scope->whereNull('location_id')
                        ->orWhere('location_id', $request->user()->primaryLocation()?->id)))
                ->orderBy('name')->get(),
            'centers' => HrCostCenter::query()
                ->when(! $this->access->global($request->user()), fn (Builder $query) => $query
                    ->where(fn (Builder $scope) => $scope->whereNull('location_id')
                        ->orWhere('location_id', $request->user()->primaryLocation()?->id)))
                ->orderBy('name')->get(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->filteredQuery($request)
            ->with([
                'employee',
                'period',
                'orgAssignment.department',
                'orgAssignment.costCenter',
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
                    'القسم عند نهاية الدورة',
                    'مركز التكلفة عند نهاية الدورة',
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
                        $row->orgAssignment?->department?->name,
                        $row->orgAssignment?->costCenter?->name,
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
        return $this->access->items($request->user())
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
                $request->filled('department_id'),
                fn (Builder $q) => $q->whereHas('orgAssignment', fn (Builder $org) =>
                    $org->where('department_id', $request->integer('department_id')))
            )
            ->when(
                $request->filled('cost_center_id'),
                fn (Builder $q) => $q->whereHas('orgAssignment', fn (Builder $org) =>
                    $org->where('cost_center_id', $request->integer('cost_center_id')))
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
