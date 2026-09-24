<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FaceAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FaceAttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'attendance-face.provider' =>
                'compreface',
            'attendance-face.compreface.base_url' =>
                'http://compreface.test',
            'attendance-face.compreface.api_key' =>
                'compreface-test-key',
            'attendance-face.compreface.det_prob_threshold' =>
                0.80,
            'attendance-face.compreface.similarity_threshold' =>
                0.78,
            'attendance-face.compreface.front_max_abs_yaw' =>
                15,
            'attendance-face.compreface.turned_min_abs_yaw' =>
                18,
            'attendance-face.compreface.min_yaw_delta' =>
                14,
            'attendance-face.challenge_ttl_seconds' =>
                90,
            'attendance-face.minimum_checkout_gap_seconds' =>
                0,
        ]);

        SystemSetting::set(
            'attendance_enabled',
            1
        );

        SystemSetting::set(
            'attendance_biometric_enabled',
            1
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    #[Test]
    public function employee_face_enrollment_is_saved_locally_after_compreface_accepts_examples(): void
    {
        Http::fake([
            'http://compreface.test/api/v1/recognition/faces*' =>
                Http::sequence()
                    ->push([
                        'image_id' => 'image-1',
                        'subject' => 'subject',
                    ], 200)
                    ->push([
                        'image_id' => 'image-2',
                        'subject' => 'subject',
                    ], 200)
                    ->push([
                        'image_id' => 'image-3',
                        'subject' => 'subject',
                    ], 200),
        ]);

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Face Employee'
        );

        $this->actingAs($manager)
            ->postJson(
                route(
                    'attendance.face.enroll',
                    $employee
                ),
                [
                    'images' => [
                        $this->image('front'),
                        $this->image('right'),
                        $this->image('left'),
                    ],
                    'consent' => true,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'status',
                'active'
            )
            ->assertJsonPath(
                'active',
                true
            );

        $profile =
            EmployeeFaceProfile::query()
                ->where(
                    'employee_id',
                    $employee->id
                )
                ->where(
                    'provider',
                    'compreface'
                )
                ->sole();

        $this->assertTrue(
            $profile->isActive()
        );

        $this->assertStringStartsWith(
            'emp-' . $employee->id . '-',
            (string) $profile->provider_face_id
        );

        $this->assertSame(
            3,
            (int) data_get(
                $profile->metadata,
                'examples_count'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $profile->metadata,
                'consent_confirmed'
            )
        );

        Http::assertSentCount(3);
    }

    #[Test]
    public function duplicate_face_cannot_be_enrolled_for_another_employee(): void
    {
        $subject = 'emp-existing-face';

        Http::fake([
            'http://compreface.test/api/v1/recognition/recognize*' =>
                Http::response(
                    $this->recognition(
                        $subject,
                        0.96,
                        0
                    ),
                    200
                ),
        ]);

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);

        $employeeA = $this->makeEmployee(
            $branch,
            'Existing Face Employee'
        );

        $employeeB = $this->makeEmployee(
            $branch,
            'Duplicate Face Employee'
        );

        $this->makeActiveProfile(
            $employeeA,
            $subject
        );

        $this->actingAs($manager)
            ->postJson(
                route(
                    'attendance.face.enroll',
                    $employeeB
                ),
                [
                    'images' => [
                        $this->image('front'),
                        $this->image('right'),
                        $this->image('left'),
                    ],
                    'consent' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'face',
            ]);

        $this->assertDatabaseCount(
            'employee_face_profiles',
            1
        );
    }

    #[Test]
    public function enrollment_requires_explicit_employee_consent(): void
    {
        Http::fake();

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Consent Employee'
        );

        $this->actingAs($manager)
            ->postJson(
                route(
                    'attendance.face.enroll',
                    $employee
                ),
                [
                    'images' => [
                        $this->image('one'),
                        $this->image('two'),
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'consent',
            ]);

        $this->assertDatabaseCount(
            'employee_face_profiles',
            0
        );

        Http::assertNothingSent();
    }

    #[Test]
    public function verified_compreface_frames_create_check_in_then_check_out(): void
    {
        $subject = 'emp-clock-test';

        Http::fake([
            'http://compreface.test/api/v1/recognition/recognize*' =>
                Http::sequence()
                    ->push(
                        $this->recognition(
                            $subject,
                            0.95,
                            2
                        ),
                        200
                    )
                    ->push(
                        $this->recognition(
                            $subject,
                            0.93,
                            27
                        ),
                        200
                    )
                    ->push(
                        $this->recognition(
                            $subject,
                            0.96,
                            1
                        ),
                        200
                    )
                    ->push(
                        $this->recognition(
                            $subject,
                            0.94,
                            -25
                        ),
                        200
                    ),
        ]);

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Clock Employee'
        );

        $this->makeActiveProfile(
            $employee,
            $subject
        );

        $firstChallenge =
            $this->actingAs($manager)
                ->postJson(
                    route(
                        'attendance.face.challenge'
                    )
                )
                ->assertOk()
                ->json('token');

        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'front_image' =>
                        $this->image('front-in'),
                    'turned_image' =>
                        $this->image('turn-in'),
                    'challenge_token' =>
                        $firstChallenge,
                    'location_id' =>
                        $branch->id,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'action',
                'check_in'
            )
            ->assertJsonPath(
                'employee.id',
                $employee->id
            );

        $record =
            AttendanceRecord::query()->sole();

        $this->assertNotNull(
            $record->check_in_at
        );

        $this->assertNull(
            $record->check_out_at
        );

        $this->assertSame(
            'compreface',
            $record->verification_provider
        );

        $this->assertSame(
            'head_pose_delta',
            data_get(
                $record->verification_metadata,
                'basic_liveness'
            )
        );

        $secondChallenge =
            $this->actingAs($manager)
                ->postJson(
                    route(
                        'attendance.face.challenge'
                    )
                )
                ->assertOk()
                ->json('token');

        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'front_image' =>
                        $this->image('front-out'),
                    'turned_image' =>
                        $this->image('turn-out'),
                    'challenge_token' =>
                        $secondChallenge,
                    'location_id' =>
                        $branch->id,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'action',
                'check_out'
            );

        $record->refresh();

        $this->assertNotNull(
            $record->check_out_at
        );

        $this->assertArrayHasKey(
            'check_in',
            $record
                ->verification_metadata[
                    'face_scans'
                ]
        );

        $this->assertArrayHasKey(
            'check_out',
            $record
                ->verification_metadata[
                    'face_scans'
                ]
        );
    }

    #[Test]
    public function insufficient_head_movement_is_rejected(): void
    {
        $subject = 'emp-liveness-test';

        Http::fake([
            'http://compreface.test/api/v1/recognition/recognize*' =>
                Http::sequence()
                    ->push(
                        $this->recognition(
                            $subject,
                            0.94,
                            2
                        ),
                        200
                    )
                    ->push(
                        $this->recognition(
                            $subject,
                            0.94,
                            7
                        ),
                        200
                    ),
        ]);

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Liveness Employee'
        );

        $this->makeActiveProfile(
            $employee,
            $subject
        );

        $token =
            $this->actingAs($manager)
                ->postJson(
                    route(
                        'attendance.face.challenge'
                    )
                )
                ->json('token');

        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'front_image' =>
                        $this->image('front'),
                    'turned_image' =>
                        $this->image('not-turned'),
                    'challenge_token' =>
                        $token,
                    'location_id' =>
                        $branch->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'liveness',
            ]);

        $this->assertDatabaseCount(
            'attendance_records',
            0
        );
    }

    #[Test]
    public function one_time_challenge_cannot_be_reused(): void
    {
        Http::fake();

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);

        $token =
            $this->actingAs($manager)
                ->postJson(
                    route(
                        'attendance.face.challenge'
                    )
                )
                ->assertOk()
                ->json('token');

        $payload = [
            'front_image' =>
                $this->image('front'),
            'turned_image' =>
                $this->image('turn'),
            'challenge_token' =>
                $token,
            'location_id' =>
                $branch->id,
        ];

        /*
         * First request consumes the challenge before recognition.
         * It fails later because Http::fake() has no valid recognition body.
         */
        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                $payload
            )
            ->assertUnprocessable();

        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'challenge_token',
            ]);
    }

    #[Test]
    public function branch_kiosk_cannot_punch_employee_from_another_branch(): void
    {
        $subject = 'emp-other-branch';

        Http::fake([
            'http://compreface.test/api/v1/recognition/recognize*' =>
                Http::sequence()
                    ->push(
                        $this->recognition(
                            $subject,
                            0.95,
                            1
                        ),
                        200
                    )
                    ->push(
                        $this->recognition(
                            $subject,
                            0.94,
                            25
                        ),
                        200
                    ),
        ]);

        $branchA = $this->makeLocation('A');
        $branchB = $this->makeLocation('B');

        $managerA =
            $this->makeManager($branchA);

        $employeeB =
            $this->makeEmployee(
                $branchB,
                'Other Branch Employee'
            );

        $this->makeActiveProfile(
            $employeeB,
            $subject
        );

        $token =
            $this->actingAs($managerA)
                ->postJson(
                    route(
                        'attendance.face.challenge'
                    )
                )
                ->json('token');

        $this->actingAs($managerA)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'front_image' =>
                        $this->image('front'),
                    'turned_image' =>
                        $this->image('turn'),
                    'challenge_token' =>
                        $token,
                    'location_id' =>
                        $branchB->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'location',
            ]);

        $this->assertDatabaseCount(
            'attendance_records',
            0
        );
    }

    #[Test]
    public function revoking_profile_deletes_subject_from_compreface(): void
    {
        $subject = 'emp-delete-test';

        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects/*' =>
                Http::response([
                    'subject' => $subject,
                ], 200),
        ]);

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Revoked Employee'
        );

        $profile =
            $this->makeActiveProfile(
                $employee,
                $subject
            );

        $this->actingAs($manager)
            ->post(
                route(
                    'attendance.face.revoke',
                    $employee
                )
            )
            ->assertRedirect();

        $profile->refresh();

        $this->assertSame(
            'revoked',
            $profile->status
        );

        $this->assertNull(
            $profile->provider_face_id
        );

        $this->assertSame(
            'deleted',
            data_get(
                $profile->metadata,
                'provider_purge'
            )
        );

        Http::assertSent(
            fn ($request): bool =>
                $request->method() === 'DELETE'
                && str_contains(
                    $request->url(),
                    '/api/v1/recognition/subjects/'
                    . $subject
                )
                && $request->hasHeader(
                    'x-api-key',
                    'compreface-test-key'
                )
        );
    }

    #[Test]
    public function manual_attendance_edit_clears_face_verification_stamp(): void
    {
        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Manual Override Employee'
        );

        $record = AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'work_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(2),
            'status' => 'present',
            'source' => 'face',
            'verification_method' => 'face',
            'verification_provider' => 'compreface',
            'verification_reference' => 'compreface:test',
            'verification_location_id' => $branch->id,
            'verification_metadata' => [
                'basic_liveness' =>
                    'head_pose_delta',
            ],
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->post(
                route(
                    'attendance.store',
                    $employee
                ),
                [
                    'work_date' =>
                        now()->toDateString(),
                    'status' => 'present',
                    'check_in_at' =>
                        now()
                            ->subHours(2)
                            ->format('Y-m-d H:i:s'),
                    'check_out_at' =>
                        now()
                            ->format('Y-m-d H:i:s'),
                    'notes' =>
                        'تعديل يدوي بعد تحقق الوجه',
                ]
            )
            ->assertRedirect();

        $record->refresh();

        $this->assertSame(
            'manual',
            $record->source
        );

        $this->assertNull(
            $record->verification_method
        );

        $this->assertNull(
            $record->verification_provider
        );

        $this->assertNull(
            $record->verification_reference
        );

        $this->assertNull(
            $record->verification_location_id
        );

        $this->assertNull(
            $record->verification_metadata
        );
    }

    #[Test]
    public function settings_user_can_test_compreface_connection(): void
    {
        Http::fake([
            'http://compreface.test/api/v1/recognition/subjects/*' =>
                Http::response([
                    'subjects' => [],
                ], 200),
        ]);

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager(
            $branch,
            [
                'settings.manage',
            ]
        );

        $this->actingAs($manager)
            ->getJson(
                route(
                    'attendance.face.connection-test'
                )
            )
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath(
                'provider',
                'compreface'
            );
    }

    private function recognition(
        string $subject,
        float $similarity,
        float $yaw
    ): array {
        return [
            'result' => [
                [
                    'box' => [
                        'probability' => 0.99,
                    ],
                    'subjects' => [
                        [
                            'subject' => $subject,
                            'similarity' =>
                                $similarity,
                        ],
                    ],
                    'pose' => [
                        'pitch' => 1.0,
                        'roll' => 0.5,
                        'yaw' => $yaw,
                    ],
                ],
            ],
        ];
    }

    private function image(
        string $label
    ): string {
        return 'data:image/jpeg;base64,'
            . base64_encode(
                'fake-image-' . $label
            );
    }

    private function makeLocation(
        string $suffix
    ): Location {
        return Location::query()->create([
            'name' =>
                'Face Branch ' . $suffix,
            'code' =>
                'FACE-'
                . $suffix
                . '-'
                . Str::upper(
                    Str::random(4)
                ),
            'type' => 'branch',
            'is_active' => true,
        ]);
    }

    private function makeEmployee(
        Location $location,
        string $name
    ): Employee {
        $employee =
            Employee::query()->create([
                'employee_number' =>
                    'FACE-EMP-'
                    . Str::upper(
                        Str::random(6)
                    ),
                'full_name' => $name,
                'employment_status' =>
                    'active',
            ]);

        $employee
            ->locations()
            ->attach(
                $location->id,
                [
                    'is_primary' => true,
                ]
            );

        return $employee;
    }

    private function makeManager(
        Location $location,
        array $extraPermissions = []
    ): User {
        $employee =
            $this->makeEmployee(
                $location,
                'Face Kiosk Manager '
                . Str::random(4)
            );

        $user =
            User::factory()->create([
                'employee_id' =>
                    $employee->id,
                'is_active' => true,
                'must_change_password' =>
                    false,
            ]);

        foreach (
            array_unique([
                'attendance.view',
                'attendance.manage',
                ...$extraPermissions,
            ])
            as $name
        ) {
            $user->givePermissionTo(
                Permission::findOrCreate(
                    $name,
                    'web'
                )
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function makeActiveProfile(
        Employee $employee,
        string $subject
    ): EmployeeFaceProfile {
        return EmployeeFaceProfile::query()
            ->create([
                'employee_id' =>
                    $employee->id,
                'provider' =>
                    'compreface',
                'provider_face_id_hash' =>
                    app(
                        FaceAttendanceService::class
                    )->hashProviderId(
                        $subject
                    ),
                'provider_face_id' =>
                    $subject,
                'status' => 'active',
                'enrolled_at' => now(),
                'activated_at' => now(),
            ]);
    }
}
