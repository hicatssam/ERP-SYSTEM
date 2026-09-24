<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FaceAttendanceService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly CompreFaceClient $compreface
    ) {
    }

    public function provider(): string
    {
        return 'compreface';
    }

    public function configured(): bool
    {
        return $this->compreface->configured();
    }

    public function connectionOk(): bool
    {
        return $this->compreface->ping();
    }

    public function baseUrl(): string
    {
        return $this->compreface->baseUrl();
    }

    public function similarityThreshold(): float
    {
        return $this->compreface
            ->similarityThreshold();
    }

    public function recordEnrollment(
        Employee $employee,
        array $images,
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

        if (count($images) < 2) {
            throw ValidationException::withMessages([
                'images' =>
                    'التقط صورتين على الأقل للموظف.',
            ]);
        }

        $provider = $this->provider();

        /*
         * Prevent the same physical face from being enrolled under two
         * employees. Recognition is checked before creating the new subject.
         * An empty collection simply returns no subject and enrollment
         * continues normally.
         */
        $possibleMatch =
            $this->compreface
                ->recognize(
                    (string) $images[0],
                    false
                );

        if (
            ! empty(
                $possibleMatch['subject']
            )
            && (float) $possibleMatch[
                'similarity'
            ] >= $this->similarityThreshold()
        ) {
            $matchedProfile =
                EmployeeFaceProfile::query()
                    ->where(
                        'provider',
                        $provider
                    )
                    ->where(
                        'provider_face_id_hash',
                        $this->hashProviderId(
                            (string) $possibleMatch[
                                'subject'
                            ]
                        )
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->whereNull(
                        'revoked_at'
                    )
                    ->first();

            if (
                $matchedProfile
                && (int) $matchedProfile
                    ->employee_id
                    !== (int) $employee->id
            ) {
                throw ValidationException::withMessages([
                    'face' =>
                        'هذا الوجه مسجل بالفعل لموظف آخر.',
                ]);
            }
        }

        $existing = EmployeeFaceProfile::query()
            ->where(
                'employee_id',
                $employee->id
            )
            ->where(
                'provider',
                $provider
            )
            ->first();

        if (
            $existing
            && $existing->isActive()
        ) {
            throw ValidationException::withMessages([
                'face' =>
                    'بصمة الوجه مفعلة بالفعل. ألغِ البصمة الحالية قبل إعادة التسجيل.',
            ]);
        }

        $subject = sprintf(
            'emp-%d-%s',
            $employee->id,
            Str::lower(
                Str::random(18)
            )
        );

        $enrollment =
            $this->compreface
                ->enrollSubject(
                    $subject,
                    $images
                );

        try {
            return DB::transaction(
                function () use (
                    $employee,
                    $actor,
                    $provider,
                    $subject,
                    $enrollment,
                    $existing
                ): EmployeeFaceProfile {
                    $profile =
                        $existing
                        ? EmployeeFaceProfile::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $existing->id
                            )
                        : new EmployeeFaceProfile([
                            'employee_id' =>
                                $employee->id,
                            'provider' =>
                                $provider,
                        ]);

                    $profile->fill([
                        'provider_face_id_hash' =>
                            $this->hashProviderId(
                                $subject
                            ),
                        'provider_face_id' =>
                            $subject,
                        'status' => 'active',
                        'enrolled_by' =>
                            $actor->id,
                        'enrolled_at' =>
                            now(),
                        'activated_at' =>
                            now(),
                        'last_verified_at' =>
                            null,
                        'revoked_at' =>
                            null,
                        'metadata' => [
                            'enrollment_source' =>
                                'camera_compreface',
                            'examples_count' =>
                                (int) (
                                    $enrollment[
                                        'examples_count'
                                    ]
                                    ?? 0
                                ),
                            'image_ids' =>
                                array_values(
                                    $enrollment[
                                        'image_ids'
                                    ]
                                    ?? []
                                ),
                            'consent_confirmed' =>
                                true,
                            'consent_confirmed_by' =>
                                $actor->id,
                            'consent_confirmed_at' =>
                                now()
                                    ->toIso8601String(),
                        ],
                    ]);

                    $profile->save();

                    return $profile->fresh([
                        'employee',
                        'enrolledBy',
                    ]);
                }
            );
        } catch (\Throwable $exception) {
            try {
                $this->compreface
                    ->deleteSubject(
                        $subject,
                        false
                    );
            } catch (\Throwable) {
                // Best-effort rollback of remote enrollment.
            }

            throw $exception;
        }
    }

    public function revokeProfile(
        EmployeeFaceProfile $profile,
        User $actor
    ): EmployeeFaceProfile {
        $this->assertConfigured();

        $profile = $profile->fresh();

        if (
            $profile->provider
                !== $this->provider()
        ) {
            throw ValidationException::withMessages([
                'face' =>
                    'هذه البصمة لا تتبع مزود CompreFace الحالي.',
            ]);
        }

        $subject = trim(
            (string) $profile
                ->provider_face_id
        );

        if ($subject !== '') {
            $this->compreface
                ->deleteSubject(
                    $subject,
                    false
                );
        }

        return DB::transaction(
            function () use (
                $profile,
                $actor
            ): EmployeeFaceProfile {
                $locked =
                    EmployeeFaceProfile::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $profile->id
                        );

                $metadata =
                    $locked->metadata ?? [];

                $metadata['revoked_by'] =
                    $actor->id;

                $metadata['revoked_at'] =
                    now()->toIso8601String();

                $metadata['provider_purge'] =
                    'deleted';

                $locked->update([
                    'status' => 'revoked',
                    'provider_face_id' =>
                        null,
                    'revoked_at' => now(),
                    'metadata' => $metadata,
                ]);

                return $locked->fresh();
            }
        );
    }

    public function punchByImages(
        User $actor,
        Location $location,
        string $frontImage,
        string $turnedImage,
        array $context = []
    ): array {
        $this->assertConfigured();

        $front =
            $this->compreface
                ->recognize(
                    $frontImage,
                    true
                );

        $turned =
            $this->compreface
                ->recognize(
                    $turnedImage,
                    true
                );

        $this->assertRecognition(
            $front,
            'اللقطة الأمامية'
        );

        $this->assertRecognition(
            $turned,
            'لقطة حركة الرأس'
        );

        if (
            $front['subject']
                !== $turned['subject']
        ) {
            throw ValidationException::withMessages([
                'liveness' =>
                    'الوجه في اللقطتين غير متطابق. أعد المحاولة.',
            ]);
        }

        $this->assertHeadMovement(
            $front,
            $turned
        );

        $subject = (string) $front[
            'subject'
        ];

        $profile =
            EmployeeFaceProfile::query()
                ->with('employee')
                ->where(
                    'provider',
                    $this->provider()
                )
                ->where(
                    'provider_face_id_hash',
                    $this->hashProviderId(
                        $subject
                    )
                )
                ->where(
                    'status',
                    'active'
                )
                ->whereNull(
                    'revoked_at'
                )
                ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'face' =>
                    'تم التعرف على وجه غير مربوط بموظف فعال.',
            ]);
        }

        $employee = $profile->employee;

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

        return DB::transaction(
            function () use (
                $actor,
                $location,
                $profile,
                $employee,
                $front,
                $turned,
                $context
            ): array {
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
                    $record
                        ?->verification_metadata
                    ?? [];

                $faceScans =
                    $metadata['face_scans']
                    ?? [];

                $faceScans[$action] = [
                    'verified_at' =>
                        $now->toIso8601String(),
                    'location_id' =>
                        $location->id,
                    'front_similarity' =>
                        $front['similarity'],
                    'turned_similarity' =>
                        $turned['similarity'],
                    'front_yaw' =>
                        data_get(
                            $front,
                            'pose.yaw'
                        ),
                    'turned_yaw' =>
                        data_get(
                            $turned,
                            'pose.yaw'
                        ),
                    'kiosk_ip' =>
                        $context['ip']
                            ?? null,
                    'user_agent' =>
                        isset(
                            $context[
                                'user_agent'
                            ]
                        )
                            ? mb_substr(
                                (string) $context[
                                    'user_agent'
                                ],
                                0,
                                300
                            )
                            : null,
                ];

                $metadata['face_scans'] =
                    $faceScans;

                $metadata[
                    'basic_liveness'
                ] = 'head_pose_delta';

                $saved =
                    $this->attendance
                        ->saveRecord(
                            $employee,
                            [
                                'work_date' =>
                                    $workDate,
                                'work_shift_id' =>
                                    $record
                                        ?->work_shift_id,
                                'status' =>
                                    'present',
                                'check_in_at' =>
                                    $record
                                        ?->check_in_at
                                    ?? $now,
                                'check_out_at' =>
                                    $action
                                        === 'check_out'
                                        ? $now
                                        : null,
                                'source' =>
                                    'face',
                                'verification_method' =>
                                    'face',
                                'verification_provider' =>
                                    $this->provider(),
                                'verification_reference' =>
                                    'compreface:'
                                    . substr(
                                        $profile
                                            ->provider_face_id_hash,
                                        0,
                                        16
                                    ),
                                'verification_location_id' =>
                                    $location->id,
                                'verification_metadata' =>
                                    $metadata,
                                'notes' =>
                                    $record?->notes,
                            ],
                            $actor
                        );

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
                    'verification' => [
                        'similarity' =>
                            min(
                                $front[
                                    'similarity'
                                ],
                                $turned[
                                    'similarity'
                                ]
                            ),
                        'front_yaw' =>
                            data_get(
                                $front,
                                'pose.yaw'
                            ),
                        'turned_yaw' =>
                            data_get(
                                $turned,
                                'pose.yaw'
                            ),
                    ],
                ];
            }
        );
    }

    public function hashProviderId(
        string $value
    ): string {
        return hash(
            'sha256',
            trim($value)
        );
    }

    private function assertRecognition(
        array $recognition,
        string $label
    ): void {
        if (
            empty(
                $recognition['subject']
            )
        ) {
            throw ValidationException::withMessages([
                'face' =>
                    $label
                    . ': لم يتم التعرف على موظف مسجل.',
            ]);
        }

        if (
            (float) $recognition[
                'similarity'
            ]
            < $this->similarityThreshold()
        ) {
            throw ValidationException::withMessages([
                'face' =>
                    $label
                    . ': درجة مطابقة الوجه أقل من الحد المطلوب.',
            ]);
        }
    }

    private function assertHeadMovement(
        array $front,
        array $turned
    ): void {
        $frontYaw = data_get(
            $front,
            'pose.yaw'
        );

        $turnedYaw = data_get(
            $turned,
            'pose.yaw'
        );

        if (
            ! is_numeric($frontYaw)
            || ! is_numeric(
                $turnedYaw
            )
        ) {
            throw ValidationException::withMessages([
                'liveness' =>
                    'CompreFace لم يرجع بيانات زاوية الرأس. تأكد أن pose plugin متاح.',
            ]);
        }

        $frontYaw = (float) $frontYaw;
        $turnedYaw = (float) $turnedYaw;

        $frontMax = (float) config(
            'attendance-face.compreface.front_max_abs_yaw',
            15
        );

        $turnedMin = (float) config(
            'attendance-face.compreface.turned_min_abs_yaw',
            18
        );

        $deltaMin = (float) config(
            'attendance-face.compreface.min_yaw_delta',
            14
        );

        if (abs($frontYaw) > $frontMax) {
            throw ValidationException::withMessages([
                'liveness' =>
                    'اللقطة الأولى يجب أن تكون والوجه للأمام.',
            ]);
        }

        if (
            abs($turnedYaw) < $turnedMin
            || abs(
                $turnedYaw
                - $frontYaw
            ) < $deltaMin
        ) {
            throw ValidationException::withMessages([
                'liveness' =>
                    'لم يتم اكتشاف حركة رأس كافية. انظر للأمام ثم لف رأسك بوضوح إلى أحد الجانبين.',
            ]);
        }
    }

    private function assertConfigured(): void
    {
        if ($this->configured()) {
            return;
        }

        throw ValidationException::withMessages([
            'face_configuration' =>
                'CompreFace غير مهيأ بعد. أضف COMPREFACE_BASE_URL وCOMPREFACE_API_KEY.',
        ]);
    }
}
