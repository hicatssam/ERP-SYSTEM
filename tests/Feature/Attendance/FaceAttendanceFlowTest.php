<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeFaceProfile;
use App\Models\FaceVerificationEvent;
use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FaceAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'attendance-face.provider' => 'faceio',
            'attendance-face.faceio.public_id' => 'fio-test-app',
            'attendance-face.faceio.webhook_token' => 'faceio-webhook-secret',
            'attendance-face.require_webhook' => true,
            'attendance-face.auth_event_ttl_seconds' => 120,
            'attendance-face.enroll_event_ttl_seconds' => 600,
            'attendance-face.minimum_checkout_gap_seconds' => 0,
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
    public function face_enrollment_stays_pending_until_signed_faceio_webhook_arrives(): void
    {
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
                    'facial_id' => 'face-enroll-001',
                    'consent' => true,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'status',
                'pending_verification'
            )
            ->assertJsonPath(
                'active',
                false
            );

        $profile =
            EmployeeFaceProfile::query()
                ->where(
                    'employee_id',
                    $employee->id
                )
                ->sole();

        $this->assertSame(
            'pending_verification',
            $profile->status
        );

        $this->faceioWebhook(
            'ENROLL',
            'face-enroll-001'
        )->assertOk();

        $profile->refresh();

        $this->assertSame(
            'active',
            $profile->status
        );

        $this->assertNotNull(
            $profile->activated_at
        );

        $this->assertDatabaseHas(
            'face_verification_events',
            [
                'event_name' => 'ENROLL',
                'app_id' => 'fio-test-app',
            ]
        );
    }

    #[Test]
    public function face_enrollment_requires_explicit_employee_consent(): void
    {
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
                    'facial_id' =>
                        'face-no-consent',
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
    }

    #[Test]
    public function invalid_faceio_webhook_token_is_rejected(): void
    {
        $response = $this
            ->withHeader(
                'WWW-Authenticate',
                'Bearer wrong-token'
            )
            ->postJson(
                route(
                    'attendance.integrations.faceio.webhook'
                ),
                [
                    'eventName' => 'AUTH',
                    'facialId' => 'face-invalid-token',
                    'appId' => 'fio-test-app',
                    'clientIp' => '203.0.113.20',
                ]
            );

        $response->assertUnauthorized();

        $this->assertDatabaseCount(
            'face_verification_events',
            0
        );
    }

    #[Test]
    public function browser_cannot_spoof_facial_id_without_recent_auth_webhook(): void
    {
        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Protected Employee'
        );

        $this->makeActiveProfile(
            $employee,
            'face-protected-001'
        );

        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'facial_id' =>
                        'face-protected-001',
                    'location_id' =>
                        $branch->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'face_confirmation',
            ]);

        $this->assertDatabaseCount(
            'attendance_records',
            0
        );
    }

    #[Test]
    public function verified_face_creates_check_in_then_check_out_and_consumes_each_auth_event_once(): void
    {
        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Clock Employee'
        );

        $this->makeActiveProfile(
            $employee,
            'face-clock-001'
        );

        $this->faceioWebhook(
            'AUTH',
            'face-clock-001',
            '203.0.113.21'
        )->assertOk();

        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'facial_id' =>
                        'face-clock-001',
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
            'face',
            $record->source
        );

        $this->assertSame(
            'face',
            $record->verification_method
        );

        $this->assertSame(
            'faceio',
            $record->verification_provider
        );

        $this->assertSame(
            $branch->id,
            $record->verification_location_id
        );

        $this->assertSame(
            1,
            FaceVerificationEvent::query()
                ->where(
                    'event_name',
                    'AUTH'
                )
                ->whereNotNull(
                    'consumed_at'
                )
                ->count()
        );

        /*
         * The consumed AUTH event cannot be replayed.
         */
        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'facial_id' =>
                        'face-clock-001',
                    'location_id' =>
                        $branch->id,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'face_confirmation',
            ]);

        $this->faceioWebhook(
            'AUTH',
            'face-clock-001',
            '203.0.113.22'
        )->assertOk();

        $this->actingAs($manager)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'facial_id' =>
                        'face-clock-001',
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
    public function branch_kiosk_cannot_punch_employee_from_another_branch(): void
    {
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
            'face-branch-b'
        );

        $this->faceioWebhook(
            'AUTH',
            'face-branch-b'
        )->assertOk();

        $this->actingAs($managerA)
            ->postJson(
                route('attendance.face.punch'),
                [
                    'facial_id' =>
                        'face-branch-b',

                    /*
                     * A branch user cannot spoof this:
                     * controller resolves the user's primary
                     * location instead.
                     */
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
    public function same_face_cannot_be_linked_to_two_employees(): void
    {
        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);

        $employeeA =
            $this->makeEmployee(
                $branch,
                'Employee A'
            );

        $employeeB =
            $this->makeEmployee(
                $branch,
                'Employee B'
            );

        config([
            'attendance-face.require_webhook' =>
                false,
        ]);

        $service =
            app(
                FaceAttendanceService::class
            );

        $service->recordEnrollment(
            $employeeA,
            'same-face-001',
            $manager,
            true
        );

        $this->actingAs($manager)
            ->postJson(
                route(
                    'attendance.face.enroll',
                    $employeeB
                ),
                [
                    'facial_id' =>
                        'same-face-001',
                    'consent' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'facial_id',
            ]);
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
            'verification_provider' => 'faceio',
            'verification_reference' => 'faceio:event:1',
            'verification_location_id' => $branch->id,
            'verification_metadata' => [
                'face_scans' => [
                    'check_in' => [
                        'event_id' => 1,
                    ],
                ],
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
    public function revoking_face_profile_purges_faceio_when_api_key_is_configured(): void
    {
        Http::fake([
            'https://api.faceio.net/deletefacialid*' =>
                Http::response([
                    'status' => 200,
                    'payload' => true,
                ], 200),
        ]);

        config([
            'attendance-face.faceio.api_key' =>
                'faceio-api-key-test',
        ]);

        $branch = $this->makeLocation('A');
        $manager = $this->makeManager($branch);
        $employee = $this->makeEmployee(
            $branch,
            'Revoked Employee'
        );

        $profile = EmployeeFaceProfile::query()
            ->create([
                'employee_id' => $employee->id,
                'provider' => 'faceio',
                'provider_face_id_hash' =>
                    app(
                        FaceAttendanceService::class
                    )->hashFaceId(
                        'face-delete-001'
                    ),
                'provider_face_id' =>
                    'face-delete-001',
                'status' => 'active',
                'enrolled_at' => now(),
                'activated_at' => now(),
            ]);

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
            function ($request): bool {
                return str_starts_with(
                    $request->url(),
                    'https://api.faceio.net/deletefacialid'
                )
                    && $request->hasHeader(
                        'WWW-Authenticate',
                        'Bearer faceio-api-key-test'
                    )
                    && str_contains(
                        $request->url(),
                        'fid=face-delete-001'
                    );
            }
        );
    }

    #[Test]
    public function wrong_faceio_application_id_is_rejected(): void
    {
        $this
            ->withHeader(
                'WWW-Authenticate',
                'Bearer faceio-webhook-secret'
            )
            ->postJson(
                route(
                    'attendance.integrations.faceio.webhook'
                ),
                [
                    'eventName' => 'AUTH',
                    'facialId' =>
                        'face-wrong-app',
                    'appId' =>
                        'another-faceio-app',
                ]
            )
            ->assertUnauthorized();

        $this->assertDatabaseCount(
            'face_verification_events',
            0
        );
    }

    private function faceioWebhook(
        string $eventName,
        string $facialId,
        string $clientIp = '203.0.113.10'
    ) {
        return $this
            ->withHeader(
                'WWW-Authenticate',
                'Bearer faceio-webhook-secret'
            )
            ->postJson(
                route(
                    'attendance.integrations.faceio.webhook'
                ),
                [
                    'eventName' =>
                        $eventName,
                    'facialId' =>
                        $facialId,
                    'appId' =>
                        'fio-test-app',
                    'clientIp' =>
                        $clientIp,
                    'details' => [
                        'timestamp' =>
                            now()->toIso8601String(),
                    ],
                ]
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
        Location $location
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
            [
                'attendance.view',
                'attendance.manage',
            ]
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
        string $facialId
    ): EmployeeFaceProfile {
        return EmployeeFaceProfile::query()
            ->create([
                'employee_id' =>
                    $employee->id,
                'provider' => 'faceio',
                'provider_face_id_hash' =>
                    app(
                        FaceAttendanceService::class
                    )->hashFaceId(
                        $facialId
                    ),
                'status' => 'active',
                'enrolled_at' => now(),
                'activated_at' => now(),
            ]);
    }
}
