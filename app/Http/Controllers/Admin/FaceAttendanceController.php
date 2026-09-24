<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\Location;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\FaceAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                'similarityThreshold' =>
                    $this->face
                        ->similarityThreshold(),
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
            'images' => [
                'required',
                'array',
                'min:2',
                'max:4',
            ],
            'images.*' => [
                'required',
                'string',
            ],
            'consent' => [
                'accepted',
            ],
        ]);

        $profile =
            $this->face->recordEnrollment(
                $employee,
                $data['images'],
                $request->user(),
                true
            );

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'attendance.face.enrolled',
            module: 'attendance',
            recordType: 'employee_face_profiles',
            recordId: $profile->id,
            newValues: [
                'employee_id' =>
                    $employee->id,
                'provider' =>
                    $profile->provider,
                'status' =>
                    $profile->status,
            ],
            metadata: [
                'employee_number' =>
                    $employee->employee_number,
                'consent_confirmed' =>
                    true,
                'examples_count' =>
                    (int) data_get(
                        $profile->metadata,
                        'examples_count',
                        0
                    ),
            ],
            ipAddress: $request->ip(),
        );

        return response()->json([
            'ok' => true,
            'status' => $profile->status,
            'active' => $profile->isActive(),
            'message' =>
                'تم تسجيل وتفعيل بصمة الوجه محليًا عبر CompreFace.',
        ]);
    }

    public function status(
        Request $request,
        Employee $employee
    ): JsonResponse {
        $this->assertEmployeeAccessible(
            $request->user(),
            $employee
        );

        $profile =
            $employee
                ->faceProfile()
                ->first();

        return response()->json([
            'ok' => true,
            'registered' =>
                (bool) $profile,
            'status' =>
                $profile?->status,
            'active' =>
                $profile?->isActive()
                ?? false,
            'activated_at' =>
                $profile
                    ?->activated_at
                    ?->toIso8601String(),
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

        $beforeStatus =
            $profile->status;

        $revoked =
            $this->face->revokeProfile(
                $profile,
                $request->user()
            );

        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'attendance.face.revoked',
            module: 'attendance',
            recordType: 'employee_face_profiles',
            recordId: $revoked->id,
            oldValues: [
                'status' => $beforeStatus,
            ],
            newValues: [
                'status' =>
                    $revoked->status,
            ],
            metadata: [
                'employee_id' =>
                    $employee->id,
                'provider_purge' =>
                    data_get(
                        $revoked->metadata,
                        'provider_purge'
                    ),
            ],
            ipAddress: $request->ip(),
        );

        return back()->with(
            'success',
            'تم إلغاء بصمة الوجه وحذف بياناتها من CompreFace.'
        );
    }

    public function challenge(
        Request $request
    ): JsonResponse {
        $this->assertConfigured();

        $token = (string) Str::uuid();

        $ttlSeconds = max(
            30,
            (int) config(
                'attendance-face.challenge_ttl_seconds',
                90
            )
        );

        $request->session()->put(
            'face_attendance_challenge',
            [
                'token' => $token,
                'expires_at' =>
                    now()
                        ->addSeconds(
                            $ttlSeconds
                        )
                        ->timestamp,
            ]
        );

        return response()->json([
            'ok' => true,
            'token' => $token,
            'expires_in' => $ttlSeconds,
            'instruction' =>
                'انظر للأمام أولًا، ثم لف رأسك بوضوح إلى أحد الجانبين.',
        ]);
    }

    public function punch(
        Request $request
    ): JsonResponse {
        $this->assertConfigured();

        $data = $request->validate([
            'front_image' => [
                'required',
                'string',
            ],
            'turned_image' => [
                'required',
                'string',
            ],
            'challenge_token' => [
                'required',
                'string',
                'uuid',
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

        $this->consumeChallenge(
            $request,
            $data['challenge_token']
        );

        $location =
            $this->resolveLocation(
                $request,
                $data['location_id']
                    ?? null
            );

        $result =
            $this->face
                ->punchByImages(
                    $request->user(),
                    $location,
                    $data['front_image'],
                    $data['turned_image'],
                    [
                        'ip' =>
                            $request->ip(),
                        'user_agent' =>
                            $request->userAgent(),
                    ]
                );

        $record = $result['record'];
        $employee = $result['employee'];
        $action = $result['action'];

        ActivityLogger::log(
            userId: $request->user()->id,
            action:
                'attendance.face.'
                . $action,
            module: 'attendance',
            recordType: 'attendance_records',
            recordId: $record->id,
            newValues: [
                'employee_id' =>
                    $employee->id,
                'work_date' =>
                    $record
                        ->work_date
                        ?->toDateString(),
                'check_in_at' =>
                    $record
                        ->check_in_at
                        ?->toIso8601String(),
                'check_out_at' =>
                    $record
                        ->check_out_at
                        ?->toIso8601String(),
            ],
            metadata: [
                'location_id' =>
                    $location->id,
                'verification_provider' =>
                    $record
                        ->verification_provider,
                'similarity' =>
                    data_get(
                        $result,
                        'verification.similarity'
                    ),
                'front_yaw' =>
                    data_get(
                        $result,
                        'verification.front_yaw'
                    ),
                'turned_yaw' =>
                    data_get(
                        $result,
                        'verification.turned_yaw'
                    ),
                'user_agent' =>
                    $request->userAgent(),
            ],
            ipAddress: $request->ip(),
        );

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
            'verification' =>
                $result['verification'],
            'message' =>
                $action === 'check_in'
                    ? 'تم تسجيل الحضور بنجاح.'
                    : 'تم تسجيل الانصراف بنجاح.',
        ]);
    }

    public function connectionTest(
        Request $request
    ): JsonResponse {
        abort_unless(
            $request->user()
                ->can('settings.manage'),
            403
        );

        return response()->json([
            'ok' =>
                $this->face
                    ->connectionOk(),
            'configured' =>
                $this->face
                    ->configured(),
            'provider' =>
                $this->face
                    ->provider(),
            'base_url' =>
                $this->face
                    ->baseUrl(),
        ]);
    }

    private function consumeChallenge(
        Request $request,
        string $token
    ): void {
        $challenge =
            $request->session()->pull(
                'face_attendance_challenge'
            );

        if (
            ! is_array($challenge)
            || ! hash_equals(
                (string) (
                    $challenge['token']
                    ?? ''
                ),
                $token
            )
            || (int) (
                $challenge['expires_at']
                ?? 0
            ) < now()->timestamp
        ) {
            throw ValidationException::withMessages([
                'challenge_token' =>
                    'انتهت جلسة التحقق أو تم استخدامها. ابدأ محاولة جديدة.',
            ]);
        }
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
            'CompreFace غير مهيأ بعد في إعدادات البيئة.'
        );
    }
}
