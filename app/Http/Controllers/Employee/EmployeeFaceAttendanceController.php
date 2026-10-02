<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSelfAttendanceRequest;
use App\Models\Location;
use App\Services\ActivityLogger;
use App\Services\AttendanceFeatureService;
use App\Services\FaceAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeFaceAttendanceController extends Controller
{
    public function __construct(
        private readonly FaceAttendanceService $face,
        private readonly AttendanceFeatureService $features
    ) {
    }

    public function challenge(Request $request): JsonResponse
    {
        [$employee] = $this->readyEmployee($request);

        $token = (string) Str::uuid();
        $ttl = max(30, (int) config('attendance-face.challenge_ttl_seconds', 90));
        $request->session()->put('employee_face_attendance_challenge', [
            'token' => $token,
            'user_id' => $request->user()->id,
            'employee_id' => $employee->id,
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ]);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'expires_in' => $ttl,
            'instruction' => 'انظر إلى الكاميرا أولًا، ثم لف رأسك بوضوح إلى أحد الجانبين.',
        ]);
    }

    public function punch(Request $request): JsonResponse
    {
        [$employee, $location] = $this->readyEmployee($request);
        $data = $request->validate([
            'front_image' => ['required', 'string', 'max:3000000'],
            'turned_image' => ['required', 'string', 'max:3000000'],
            'challenge_token' => ['required', 'uuid'],
        ]);

        $challenge = $request->session()->pull('employee_face_attendance_challenge');
        if (! is_array($challenge)
            || ! hash_equals((string) ($challenge['token'] ?? ''), $data['challenge_token'])
            || (int) ($challenge['user_id'] ?? 0) !== (int) $request->user()->id
            || (int) ($challenge['employee_id'] ?? 0) !== (int) $employee->id
            || (int) ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'challenge_token' => 'انتهت جلسة التحقق أو تم استخدامها. ابدأ محاولة جديدة.',
            ]);
        }

        $result = $this->face->punchByImages(
            $request->user(),
            $location,
            $data['front_image'],
            $data['turned_image'],
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'channel' => 'employee_portal',
            ],
            $employee
        );

        $record = $result['record'];
        ActivityLogger::log(
            userId: $request->user()->id,
            action: 'attendance.face.self_'.$result['action'],
            module: 'attendance',
            recordType: 'attendance_records',
            recordId: $record->id,
            newValues: [
                'employee_id' => $employee->id,
                'work_date' => $record->work_date?->toDateString(),
                'check_in_at' => $record->check_in_at?->toIso8601String(),
                'check_out_at' => $record->check_out_at?->toIso8601String(),
            ],
            metadata: ['location_id' => $location->id, 'channel' => 'employee_portal'],
            ipAddress: $request->ip()
        );

        return response()->json([
            'ok' => true,
            'action' => $result['action'],
            'attendance' => [
                'work_date' => $record->work_date?->toDateString(),
                'check_in_at' => $record->check_in_at?->format('H:i'),
                'check_out_at' => $record->check_out_at?->format('H:i'),
            ],
            'message' => $result['action'] === 'check_in'
                ? 'تم تسجيل الحضور في سجلك.'
                : 'تم تسجيل الانصراف في سجلك.',
        ]);
    }

    /** @return array{Employee, Location} */
    private function readyEmployee(Request $request): array
    {
        abort_unless($this->features->employeeFacePunchEnabled(), 403, 'تسجيل الوجه من بوابة الموظف غير مفعّل.');
        abort_unless($this->face->configured(), 503, 'خدمة بصمة الوجه غير مهيأة.');

        $employee = $request->user()->employee;
        abort_unless($employee && $employee->isActive(), 403, 'الحساب غير مرتبط بموظف نشط.');
        abort_if(EmployeeSelfAttendanceRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->where(fn ($query) => $query
                ->whereDate('work_date', now()->toDateString())
                ->orWhere(fn ($open) => $open->whereNull('check_out_at')
                    ->where('check_in_at', '>=', now()->subDay())))
            ->exists(), 409, 'لديك تسجيل ذاتي مفتوح أو قيد المراجعة؛ راجع المسؤول قبل استخدام بصمة الوجه.');
        $profile = $employee->faceProfile;
        abort_unless($profile && $profile->isActive() && $profile->provider === $this->face->provider(),
            403, 'سجّل بصمة وجهك لدى المسؤول قبل استخدام هذا الخيار.');

        $today = now()->toDateString();
        $assignment = $employee->employeeLocations()->with('location')
            ->where('is_primary', true)
            ->where(fn ($query) => $query->whereNull('started_at')->orWhereDate('started_at', '<=', $today))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $today))
            ->orderByDesc('started_at')->first();
        $location = $assignment?->location;
        abort_unless($location && $location->is_active, 403, 'لا يوجد فرع نشط مرتبط بالموظف.');

        return [$employee, $location];
    }
}
