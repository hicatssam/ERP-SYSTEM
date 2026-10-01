<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeHrProfile;
use App\Models\EmployeeLeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrDashboardController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $dates = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        if (Carbon::parse($dates['from'])->diffInDays(Carbon::parse($dates['to'])) > 92) {
            throw ValidationException::withMessages([
                'to' => 'اختر فترة لا تتجاوز 93 يومًا لكل تقرير.',
            ]);
        }
        $ids = Employee::query()->accessibleBy($request->user())->select('employees.id');

        return response()->streamDownload(function () use ($ids, $dates): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['الموظف', 'التاريخ', 'الحالة', 'دقائق العمل', 'دقائق التأخير', 'دقائق الانصراف المبكر', 'دقائق الإضافي']);
            AttendanceRecord::query()->whereIn('employee_id', $ids)
                ->whereDate('work_date', '>=', $dates['from'])
                ->whereDate('work_date', '<=', $dates['to'])
                ->where(fn ($query) => $query->whereIn('status', ['absent', 'leave'])
                    ->orWhere('late_minutes', '>', 0)
                    ->orWhere('early_leave_minutes', '>', 0)
                    ->orWhere('overtime_minutes', '>', 0))
                ->with('employee:id,full_name')
                ->chunkById(500, function ($records) use ($out): void {
                    foreach ($records as $record) {
                        $name = $record->employee?->full_name ?? '';
                        if (preg_match('/^[=+\-@]/u', $name)) {
                            $name = "'".$name;
                        }
                        fputcsv($out, [
                            $name, $record->work_date?->format('Y-m-d'), $record->status,
                            $record->worked_minutes, $record->late_minutes,
                            $record->early_leave_minutes, $record->overtime_minutes,
                        ]);
                    }
                });
            fclose($out);
        }, 'hr-attendance-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function index(Request $request): View
    {
        $staff = Employee::query()->accessibleBy($request->user());
        $ids = (clone $staff)->select('employees.id');
        $today = now()->toDateString();
        $deadline = now()->addDays(30)->toDateString();

        $leave = EmployeeLeaveRequest::query()->whereIn('employee_id', clone $ids)
            ->where('status', 'pending');
        $corrections = AttendanceCorrectionRequest::query()->whereIn('employee_id', clone $ids)
            ->where('status', 'pending');
        $absences = AttendanceRecord::query()->whereIn('employee_id', clone $ids)
            ->whereDate('work_date', $today)->where('status', 'absent');

        $documents = EmployeeDocument::query()->whereIn('employee_id', clone $ids)
            ->whereNotNull('expires_on')->whereDate('expires_on', '<=', $deadline);
        $contracts = EmployeeHrProfile::query()->whereIn('employee_id', clone $ids)
            ->whereNotNull('contract_ends_on')->whereDate('contract_ends_on', '<=', $deadline);

        return view('admin.hr.dashboard', [
            'employeeCount' => (clone $staff)->count(),
            'leaveCount' => (clone $leave)->count(),
            'correctionCount' => (clone $corrections)->count(),
            'absenceCount' => (clone $absences)->count(),
            'leaves' => (clone $leave)->with('employee')->oldest()->limit(10)->get(),
            'corrections' => (clone $corrections)->with('employee')->oldest()->limit(10)->get(),
            'documents' => $request->user()->can('hr.documents.view')
                ? $documents->with('employee')->orderBy('expires_on')->limit(10)->get()
                : collect(),
            'contracts' => $request->user()->can('hr.documents.view')
                ? $contracts->with('employee')->orderBy('contract_ends_on')->limit(10)->get()
                : collect(),
        ]);
    }
}
