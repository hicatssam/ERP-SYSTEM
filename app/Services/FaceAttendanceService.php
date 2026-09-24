<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\FaceVerificationEvent;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FaceAttendanceService
{
    public function __construct(
        private readonly AttendanceService $attendance
    ) {
    }

    public function provider(): string
    {
        return (string) config(
            'attendance-face.provider',
            'faceio'
        );
    }

    public function publicId(): ?string
    {
        $value = trim(
            (string) config(
                'attendance-face.faceio.public_id'
            )
        );

        return $value !== ''
            ? $value
            : null;
    }

    public function webhookToken(): ?string
    {
        $value = trim(
            (string) config(
                'attendance-face.faceio.webhook_token'
            )
        );

        return $value !== ''
            ? $value
            : null;
    }

    public function apiKey(): ?string
    {
        $value = trim(
            (string) config(
                'attendance-face.faceio.api_key'
            )
        );

        return $value !== ''
            ? $value
            : null;
    }

    public function canPurgeProviderProfile(): bool
    {
        return (bool) $this->apiKey();
    }

    public function requiresWebhook(): bool
    {
        return (bool) config(
            'attendance-face.require_webhook',
            true
        );
    }

    public function configured(): bool
    {
        if (! $this->publicId()) {
            return false;
        }

        return ! $this->requiresWebhook()
            || (bool) $this->webhookToken();
    }

    public function hashFaceId(string $facialId): string
    {
        return hash(
            'sha256',
            trim($facialId)
        );
    }

    public function recordEnrollment(
        Employee $employee,
        string $facialId,
        User $actor,
        bool $consentConfirmed = false
    ): EmployeeFaceProfile {
        $this->assertConfigured();

        if (! $consentConfirmed) {
            throw ValidationException::withMessages([
                'consent' =>
                    'يجب تأكيد موافقة الموظف قبل تسجيل بيانات الوجه.',
            ]);
        }

        $facialId = trim($facialId);

        if ($facialId === '') {
            throw ValidationException::withMessages([
                'facial_id' =>
                    'لم يتم استلام معرف الوجه.',
            ]);
        }

        $hash = $this->hashFaceId($facialId);
        $provider = $this->provider();

        return DB::transaction(
            function () use (
                $employee,
                $actor,
                $hash,
                $provider,
                $facialId
            ): EmployeeFaceProfile {
                $duplicate = EmployeeFaceProfile::query()
                    ->where('provider', $provider)
                    ->where(
                        'provider_face_id_hash',
                        $hash
                    )
                    ->where(
                        'employee_id',
                        '!=',
                        $employee->id
                    )
                    ->lockForUpdate()
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'facial_id' =>
                            'هذا الوجه مرتبط بموظف آخر بالفعل.',
                    ]);
                }

                $profile = EmployeeFaceProfile::query()
                    ->where(
                        'employee_id',
                        $employee->id
                    )
                    ->where(
                        'provider',
                        $provider
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $profile) {
                    $profile = new EmployeeFaceProfile([
                        'employee_id' =>
                            $employee->id,
                        'provider' =>
                            $provider,
                    ]);
                }

                $profile->fill([
                    'provider_face_id_hash' =>
                        $hash,
                    'provider_face_id' =>
                        $facialId,
                    'status' =>
                        $this->requiresWebhook()
                            ? 'pending_verification'
                            : 'active',
                    'enrolled_by' =>
                        $actor->id,
                    'enrolled_at' =>
                        now(),
                    'activated_at' =>
                        $this->requiresWebhook()
                            ? null
                            : now(),
                    'last_verified_at' =>
                        null,
                    'revoked_at' =>
                        null,
                    'metadata' => [
                        'enrollment_source' =>
                            'faceio_widget',
                        'consent_confirmed' =>
                            true,
                        'consent_confirmed_by' =>
                            $actor->id,
                        'consent_confirmed_at' =>
                            now()->toIso8601String(),
                    ],
                ]);

                $profile->save();

                if ($this->requiresWebhook()) {
                    $event = $this
                        ->recentEventQuery(
                            'ENROLL',
                            $hash,
                            (int) config(
                                'attendance-face.enroll_event_ttl_seconds',
                                600
                            )
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($event) {
                        $this->activateProfile(
                            $profile,
                            $event
                        );
                    }
                }

                return $profile->fresh([
                    'employee',
                    'enrolledBy',
                ]);
            }
        );
    }

    public function revokeProfile(
        EmployeeFaceProfile $profile,
        User $actor
    ): EmployeeFaceProfile {
        $profile = $profile->fresh();

        $purgeState = 'not_configured';

        if (
            $profile->provider === 'faceio'
            && $profile->provider_face_id
            && $this->apiKey()
        ) {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'WWW-Authenticate' =>
                        'Bearer ' . $this->apiKey(),
                ])
                ->get(
                    (string) config(
                        'attendance-face.faceio.delete_url'
                    ),
                    [
                        'fid' =>
                            $profile->provider_face_id,
                    ]
                );

            $deleted =
                $response->successful()
                && (int) $response->json(
                    'status'
                ) === 200
                && (bool) $response->json(
                    'payload'
                );

            if (! $deleted) {
                throw ValidationException::withMessages([
                    'face' =>
                        'تعذر حذف بصمة الوجه من FACEIO. لم يتم إلغاء الربط المحلي حتى لا تصبح البيانات غير متزامنة.',
                ]);
            }

            $purgeState = 'deleted';
        }

        return DB::transaction(
            function () use (
                $profile,
                $actor,
                $purgeState
            ): EmployeeFaceProfile {
                $profile = EmployeeFaceProfile::query()
                    ->lockForUpdate()
                    ->findOrFail($profile->id);

                $metadata =
                    $profile->metadata ?? [];

                $metadata['revoked_by'] =
                    $actor->id;

                $metadata['provider_purge'] =
                    $purgeState;

                $profile->update([
                    'status' => 'revoked',
                    'provider_face_id' =>
                        $purgeState === 'deleted'
                            ? null
                            : $profile->provider_face_id,
                    'revoked_at' => now(),
                    'metadata' => $metadata,
                ]);

                return $profile->fresh();
            }
        );
    }

    public function storeWebhookEvent(
        array $data,
        string $rawBody
    ): FaceVerificationEvent {
        $eventName = strtoupper(
            trim((string) ($data['eventName'] ?? ''))
        );

        $facialId = trim(
            (string) ($data['facialId'] ?? '')
        );

        if (
            ! in_array(
                $eventName,
                ['ENROLL', 'AUTH', 'DELETION'],
                true
            )
            || $facialId === ''
        ) {
            throw ValidationException::withMessages([
                'event' =>
                    'حدث FACEIO غير صالح.',
            ]);
        }

        $hash =
            $this->hashFaceId($facialId);

        /*
         * FACEIO does not document a unique webhook event ID. The AUTH body
         * can therefore be identical for the same employee on two different
         * punches. Deduplicate only inside a short retry window instead of
         * making the raw body globally unique forever.
         */
        $rawFingerprintSource =
            $rawBody !== ''
                ? $rawBody
                : json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                );

        $retryWindow = 30;
        $timeBucket = (int) floor(
            now()->timestamp
            / $retryWindow
        );

        $fingerprint = hash(
            'sha256',
            $rawFingerprintSource
            . '|'
            . $timeBucket
        );

        return DB::transaction(
            function () use (
                $data,
                $eventName,
                $hash,
                $fingerprint
            ): FaceVerificationEvent {
                $event =
                    FaceVerificationEvent::query()
                        ->firstOrCreate(
                            [
                                'fingerprint' =>
                                    $fingerprint,
                            ],
                            [
                                'provider' =>
                                    $this->provider(),
                                'event_name' =>
                                    $eventName,
                                'provider_face_id_hash' =>
                                    $hash,
                                'app_id' =>
                                    $data['appId']
                                        ?? null,
                                'client_ip' =>
                                    $data['clientIp']
                                        ?? null,
                                'occurred_at' =>
                                    $this->eventTimestamp(
                                        $data
                                    ),
                                'received_at' =>
                                    now(),
                                'payload' =>
                                    isset($data['payload'])
                                        ? (
                                            is_array(
                                                $data['payload']
                                            )
                                                ? $data['payload']
                                                : [
                                                    'value' =>
                                                        $data[
                                                            'payload'
                                                        ],
                                                ]
                                        )
                                        : null,
                                'metadata' => [
                                    'details' =>
                                        $data['details']
                                            ?? null,
                                ],
                            ]
                        );

                if ($eventName === 'ENROLL') {
                    $profile =
                        EmployeeFaceProfile::query()
                            ->where(
                                'provider',
                                $this->provider()
                            )
                            ->where(
                                'provider_face_id_hash',
                                $hash
                            )
                            ->where(
                                'status',
                                'pending_verification'
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($profile) {
                        $this->activateProfile(
                            $profile,
                            $event
                        );
                    }
                }

                if ($eventName === 'DELETION') {
                    EmployeeFaceProfile::query()
                        ->where(
                            'provider',
                            $this->provider()
                        )
                        ->where(
                            'provider_face_id_hash',
                            $hash
                        )
                        ->update([
                            'status' =>
                                'revoked',
                            'provider_face_id' =>
                                null,
                            'revoked_at' =>
                                now(),
                        ]);

                    if (! $event->consumed_at) {
                        $event->update([
                            'consumed_at' =>
                                now(),
                        ]);
                    }
                }

                return $event->fresh();
            }
        );
    }

    public function punch(
        User $actor,
        Location $location,
        string $facialId,
        array $context = []
    ): array {
        $this->assertConfigured();

        $hash = $this->hashFaceId(
            $facialId
        );

        return DB::transaction(
            function () use (
                $actor,
                $location,
                $hash,
                $context
            ): array {
                $profile =
                    EmployeeFaceProfile::query()
                        ->with('employee')
                        ->where(
                            'provider',
                            $this->provider()
                        )
                        ->where(
                            'provider_face_id_hash',
                            $hash
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->whereNull(
                            'revoked_at'
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $profile) {
                    throw ValidationException::withMessages([
                        'face' =>
                            'الوجه غير مربوط بموظف فعال في النظام.',
                    ]);
                }

                $employee =
                    $profile->employee;

                if (
                    ! $employee
                    || ! $employee->isActive()
                ) {
                    throw ValidationException::withMessages([
                        'face' =>
                            'الموظف غير فعال حاليًا.',
                    ]);
                }

                $belongsToLocation =
                    $employee
                        ->employeeLocations()
                        ->where(
                            'location_id',
                            $location->id
                        )
                        ->whereNull('ended_at')
                        ->exists();

                if (! $belongsToLocation) {
                    throw ValidationException::withMessages([
                        'location' =>
                            'هذا الموظف غير مرتبط بفرع جهاز الحضور الحالي.',
                    ]);
                }

                $verificationEvent = null;

                if ($this->requiresWebhook()) {
                    $verificationEvent = $this
                        ->recentEventQuery(
                            'AUTH',
                            $hash,
                            (int) config(
                                'attendance-face.auth_event_ttl_seconds',
                                120
                            )
                        )
                        ->lockForUpdate()
                        ->first();

                    if (! $verificationEvent) {
                        throw ValidationException::withMessages([
                            'face_confirmation' =>
                                'بانتظار تأكيد FACEIO الآمن. أعد المحاولة بعد لحظة.',
                        ]);
                    }
                }

                $workDate =
                    now()->toDateString();

                $record =
                    AttendanceRecord::query()
                        ->where(
                            'employee_id',
                            $employee->id
                        )
                        ->whereDate(
                            'work_date',
                            $workDate
                        )
                        ->lockForUpdate()
                        ->first();

                if ($record?->approved_at) {
                    throw ValidationException::withMessages([
                        'attendance' =>
                            'سجل حضور اليوم معتمد ولا يمكن تعديله من الكشك.',
                    ]);
                }

                if (
                    $record
                    && $record->status
                        !== 'present'
                ) {
                    throw ValidationException::withMessages([
                        'attendance' =>
                            'يوجد للموظف سجل يومي بحالة أخرى ولا يمكن استبداله ببصمة وجه.',
                    ]);
                }

                $now = now();
                $action = 'check_in';

                if ($record?->check_in_at) {
                    if ($record->check_out_at) {
                        throw ValidationException::withMessages([
                            'attendance' =>
                                'تم تسجيل الحضور والانصراف لهذا الموظف اليوم بالفعل.',
                        ]);
                    }

                    $minimumGap = max(
                        0,
                        (int) config(
                            'attendance-face.minimum_checkout_gap_seconds',
                            60
                        )
                    );

                    if (
                        $record->check_in_at
                            ->copy()
                            ->addSeconds(
                                $minimumGap
                            )
                            ->gt($now)
                    ) {
                        throw ValidationException::withMessages([
                            'attendance' =>
                                'تم تسجيل الحضور قبل لحظات. انتظر قليلًا قبل تسجيل الانصراف.',
                        ]);
                    }

                    $action = 'check_out';
                }

                $metadata =
                    $record?->verification_metadata
                    ?? [];

                $faceScans =
                    $metadata['face_scans']
                    ?? [];

                $faceScans[$action] = [
                    'event_id' =>
                        $verificationEvent?->id,
                    'verified_at' =>
                        $now->toIso8601String(),
                    'location_id' =>
                        $location->id,
                    'kiosk_ip' =>
                        $context['ip']
                            ?? null,
                    'user_agent' =>
                        isset($context['user_agent'])
                            ? mb_substr(
                                (string) $context['user_agent'],
                                0,
                                300
                            )
                            : null,
                ];

                $metadata['face_scans'] =
                    $faceScans;

                $saved =
                    $this->attendance->saveRecord(
                        $employee,
                        [
                            'work_date' =>
                                $workDate,
                            'work_shift_id' =>
                                $record?->work_shift_id,
                            'status' =>
                                'present',
                            'check_in_at' =>
                                $record?->check_in_at
                                    ?? $now,
                            'check_out_at' =>
                                $action === 'check_out'
                                    ? $now
                                    : null,
                            'source' =>
                                'face',
                            'verification_method' =>
                                'face',
                            'verification_provider' =>
                                $this->provider(),
                            'verification_reference' =>
                                $verificationEvent
                                    ? 'faceio:event:'
                                        . $verificationEvent->id
                                    : 'faceio:client-confirmed',
                            'verification_location_id' =>
                                $location->id,
                            'verification_metadata' =>
                                $metadata,
                            'notes' =>
                                $record?->notes,
                        ],
                        $actor
                    );

                if ($verificationEvent) {
                    $verificationEvent->update([
                        'consumed_at' =>
                            now(),
                    ]);
                }

                $profile->update([
                    'last_verified_at' =>
                        now(),
                ]);

                return [
                    'action' => $action,
                    'employee' =>
                        $employee->fresh(),
                    'record' =>
                        $saved->fresh('shift'),
                    'location' =>
                        $location,
                ];
            }
        );
    }

    private function recentEventQuery(
        string $eventName,
        string $hash,
        int $ttlSeconds
    ) {
        return FaceVerificationEvent::query()
            ->where(
                'provider',
                $this->provider()
            )
            ->where(
                'event_name',
                $eventName
            )
            ->where(
                'provider_face_id_hash',
                $hash
            )
            ->where(
                'app_id',
                $this->publicId()
            )
            ->whereNull('consumed_at')
            ->where(
                'received_at',
                '>=',
                now()->subSeconds(
                    max(1, $ttlSeconds)
                )
            )
            ->latest('received_at')
            ->latest('id');
    }

    private function activateProfile(
        EmployeeFaceProfile $profile,
        FaceVerificationEvent $event
    ): void {
        $profile->update([
            'status' => 'active',
            'activated_at' => now(),
            'revoked_at' => null,
        ]);

        if (! $event->consumed_at) {
            $event->update([
                'consumed_at' => now(),
            ]);
        }
    }

    private function eventTimestamp(
        array $data
    ): ?Carbon {
        $timestamp =
            data_get(
                $data,
                'details.timestamp'
            );

        if (! is_string($timestamp)) {
            return null;
        }

        try {
            return Carbon::parse(
                $timestamp
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertConfigured(): void
    {
        if ($this->configured()) {
            return;
        }

        throw ValidationException::withMessages([
            'face_configuration' =>
                'بصمة الوجه غير مهيأة بعد. أضف FACEIO_PUBLIC_ID وFACEIO_WEBHOOK_TOKEN في إعدادات البيئة.',
        ]);
    }
}
