<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\Location;
use App\Models\User;
use App\Services\FaceAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FaceAttendanceController extends Controller
{
    public function __construct(
        private readonly FaceAttendanceService $face
    ) {
    }

    public function kiosk(Request $request): View
    {
        $this->assertConfigured();

        $location = $this->resolveLocation(
            $request
        );

        $locations = $request->user()->isAdmin()
            ? Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->get()
            : collect([$location]);

        return view(
            'admin.attendance.face-kiosk',
            [
                'location' => $location,
                'locations' => $locations,
                'faceioPublicId' =>
                    $this->face->publicId(),
                'faceioScriptUrl' =>
                    config(
                        'attendance-face.faceio.script_url'
                    ),
                'webhookRequired' =>
                    $this->face->requiresWebhook(),
            ]
        );
    }

    public function enrollPage(
        Request $request,
        Employee $employee
    ): View {
        $this->assertConfigured();

        $this->assertEmployeeAccessible(
            $request->user(),
            $employee
        );

        $employee->loadMissing(
            'faceProfile'
        );

        return view(
            'admin.attendance.face-enroll',
            [
                'employee' => $employee,
                'faceProfile' =>
                    $employee->faceProfile,
                'faceioPublicId' =>
                    $this->face->publicId(),
                'faceioScriptUrl' =>
                    config(
                        'attendance-face.faceio.script_url'
                    ),
                'webhookRequired' =>
                    $this->face->requiresWebhook(),
            ]
        );
    }

    public function enroll(
        Request $request,
        Employee $employee
    ): JsonResponse {
        $this->assertConfigured();

        $this->assertEmployeeAccessible(
            $request->user(),
            $employee
        );

        $data = $request->validate([
            'facial_id' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $profile =
            $this->face->recordEnrollment(
                $employee,
                $data['facial_id'],
                $request->user()
            );

        return response()->json([
            'ok' => true,
            'status' => $profile->status,
            'active' =>
                $profile->isActive(),
            'message' =>
                $profile->isActive()
                    ? 'تم تفعيل بصمة الوجه للموظف.'
                    : 'تم تسجيل الوجه وبانتظار تأكيد FACEIO الآمن.',
        ]);
    }

    public function revoke(
        Request $request,
        Employee $employee
    ): RedirectResponse {
        $this->assertConfigured();

        $this->assertEmployeeAccessible(
            $request->user(),
            $employee
        );

        $profile =
            $employee
                ->faceProfile()
                ->first();

        abort_unless(
            $profile,
            404,
            'لا توجد بصمة وجه لهذا الموظف.'
        );

        $this->face->revokeProfile(
            $profile,
            $request->user()
        );

        return back()->with(
            'success',
            'تم إلغاء بصمة الوجه للموظف.'
        );
    }

    public function punch(
        Request $request
    ): JsonResponse {
        $this->assertConfigured();

        $data = $request->validate([
            'facial_id' => [
                'required',
                'string',
                'max:255',
            ],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'locations',
                    'id'
                ),
            ],
        ]);

        $location =
            $this->resolveLocation(
                $request,
                $data['location_id']
                    ?? null
            );

        $result = $this->face->punch(
            $request->user(),
            $location,
            $data['facial_id']
        );

        $record = $result['record'];
        $employee = $result['employee'];
        $action = $result['action'];

        return response()->json([
            'ok' => true,
            'action' => $action,
            'employee' => [
                'id' => $employee->id,
                'name' =>
                    $employee->full_name,
                'number' =>
                    $employee->employee_number,
            ],
            'attendance' => [
                'work_date' =>
                    $record
                        ->work_date
                        ?->toDateString(),
                'check_in_at' =>
                    $record
                        ->check_in_at
                        ?->format('H:i'),
                'check_out_at' =>
                    $record
                        ->check_out_at
                        ?->format('H:i'),
                'late_minutes' =>
                    (int) $record->late_minutes,
                'shift' =>
                    $record->shift?->name,
            ],
            'message' =>
                $action === 'check_in'
                    ? 'تم تسجيل الحضور بنجاح.'
                    : 'تم تسجيل الانصراف بنجاح.',
        ]);
    }

    private function resolveLocation(
        Request $request,
        ?int $requestedLocationId = null
    ): Location {
        $user = $request->user();

        if ($user->isAdmin()) {
            $locationId =
                $requestedLocationId
                ?: $request->integer(
                    'location_id'
                )
                ?: $user
                    ->primaryLocation()
                    ?->id;

            if ($locationId) {
                return Location::query()
                    ->branches()
                    ->active()
                    ->findOrFail(
                        $locationId
                    );
            }

            $first = Location::query()
                ->branches()
                ->active()
                ->orderBy('name')
                ->first();

            abort_unless(
                $first,
                403,
                'لا يوجد فرع فعال لتشغيل كشك الحضور.'
            );

            return $first;
        }

        $location =
            $user->primaryLocation();

        abort_unless(
            $location
            && (bool) $location->is_active,
            403,
            'لا يوجد فرع فعال مرتبط بالمستخدم.'
        );

        return $location;
    }

    private function assertEmployeeAccessible(
        User $user,
        Employee $employee
    ): void {
        if ($user->isAdmin()) {
            return;
        }

        $locationId =
            $user->primaryLocation()?->id;

        abort_unless(
            $locationId,
            403,
            'لا يوجد فرع مرتبط بالمستخدم.'
        );

        $allowed =
            $employee
                ->employeeLocations()
                ->where(
                    'location_id',
                    $locationId
                )
                ->whereNull('ended_at')
                ->exists();

        abort_unless(
            $allowed,
            403,
            'لا يمكنك إدارة بصمة وجه موظف تابع لفرع آخر.'
        );
    }

    private function assertConfigured(): void
    {
        abort_unless(
            $this->face->configured(),
            503,
            'بصمة الوجه غير مهيأة بعد في إعدادات البيئة.'
        );
    }
}
