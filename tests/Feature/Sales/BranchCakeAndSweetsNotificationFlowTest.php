<?php

namespace Tests\Feature\Sales;

use App\Enums\ShowroomCakeRequestStatus;
use App\Enums\ShowroomSweetsRequestStatus;
use App\Models\Employee;
use App\Models\Location;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Notifications\ShowroomCakeRequestNotification;
use App\Notifications\ShowroomSweetsRequestNotification;
use App\Services\Notifications\ShowroomCakeRequestNotifier;
use App\Services\Notifications\ShowroomSweetsRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchCakeAndSweetsNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private Location $branch;
    private Location $otherBranch;
    private Location $factory;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->branch =
            $this->makeLocation(
                'Branch A',
                'branch'
            );

        $this->otherBranch =
            $this->makeLocation(
                'Branch B',
                'branch'
            );

        $this->factory =
            $this->makeLocation(
                'Main Factory',
                'factory'
            );

        $this->actor =
            $this->makeUser(
                $this->branch,
                [
                    'showroom_cake_requests.view',
                    'showroom_cake_requests.update_status',
                    'showroom_sweets_requests.view',
                    'showroom_sweets_requests.create',
                    'showroom_sweets_requests.cancel',
                ]
            );
    }

    #[Test]
    public function both_branch_request_types_expose_the_same_four_stage_workflow(): void
    {
        $expected = [
            'pending',
            'in_progress',
            'ready',
            'completed',
            'cancelled',
        ];

        $this->assertSame(
            $expected,
            array_map(
                fn (ShowroomCakeRequestStatus $status) =>
                    $status->value,
                ShowroomCakeRequestStatus::workflowCases()
            )
        );

        $this->assertSame(
            $expected,
            array_map(
                fn (ShowroomSweetsRequestStatus $status) =>
                    $status->value,
                ShowroomSweetsRequestStatus::workflowCases()
            )
        );

        $this->assertSame(
            ['in_progress', 'cancelled'],
            array_map(
                fn ($status) => $status->value,
                ShowroomCakeRequestStatus::Pending
                    ->allowedTransitions()
            )
        );

        $this->assertSame(
            ['ready', 'cancelled'],
            array_map(
                fn ($status) => $status->value,
                ShowroomSweetsRequestStatus::InProgress
                    ->allowedTransitions()
            )
        );

        $this->assertSame(
            ['completed', 'cancelled'],
            array_map(
                fn ($status) => $status->value,
                ShowroomCakeRequestStatus::Ready
                    ->allowedTransitions()
            )
        );
    }

    #[Test]
    public function legacy_branch_request_statuses_normalize_to_the_new_workflow(): void
    {
        $this->assertSame(
            'pending',
            ShowroomCakeRequestStatus::Submitted
                ->workflowValue()
        );

        $this->assertSame(
            'completed',
            ShowroomCakeRequestStatus::Fulfilled
                ->workflowValue()
        );

        $this->assertSame(
            'ready',
            ShowroomSweetsRequestStatus::OutForDelivery
                ->workflowValue()
        );

        $this->assertSame(
            'completed',
            ShowroomSweetsRequestStatus::ReceivedAtBranch
                ->workflowValue()
        );

        $this->assertSame(
            'cancelled',
            ShowroomSweetsRequestStatus::Rejected
                ->workflowValue()
        );
    }

    #[Test]
    public function new_branch_cake_request_notifies_factory_and_admin_but_not_wrong_location_or_normal_actor(): void
    {
        Notification::fake();

        $factoryRecipient =
            $this->makeUser(
                $this->factory,
                [
                    'showroom_cake_requests.view',
                    'showroom_cake_requests.update_status',
                ]
            );

        $wrongBranch =
            $this->makeUser(
                $this->otherBranch,
                [
                    'showroom_cake_requests.view',
                    'showroom_cake_requests.update_status',
                ]
            );

        $admin =
            $this->makeAdmin(
                $this->otherBranch
            );

        $inactive =
            $this->makeUser(
                $this->factory,
                [
                    'showroom_cake_requests.view',
                ],
                false
            );

        $request =
            $this->makeCakeRequest(
                'pending'
            );

        ShowroomCakeRequestNotifier::requestCreated(
            $request,
            $this->actor
        );

        Notification::assertSentTo(
            $factoryRecipient,
            ShowroomCakeRequestNotification::class
        );

        Notification::assertSentTo(
            $admin,
            ShowroomCakeRequestNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranch,
            ShowroomCakeRequestNotification::class
        );

        Notification::assertNotSentTo(
            $inactive,
            ShowroomCakeRequestNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            ShowroomCakeRequestNotification::class
        );
    }

    #[Test]
    public function new_branch_sweets_request_notifies_factory_and_admin_but_not_wrong_location_or_normal_actor(): void
    {
        Notification::fake();

        $factoryRecipient =
            $this->makeUser(
                $this->factory,
                [
                    'showroom_sweets_requests.view',
                    'showroom_sweets_requests.start',
                ]
            );

        $wrongBranch =
            $this->makeUser(
                $this->otherBranch,
                [
                    'showroom_sweets_requests.view',
                    'showroom_sweets_requests.start',
                ]
            );

        $admin =
            $this->makeAdmin(
                $this->otherBranch
            );

        $request =
            $this->makeSweetsRequest(
                'pending'
            );

        ShowroomSweetsRequestNotifier::requestCreated(
            $request,
            $this->actor
        );

        Notification::assertSentTo(
            $factoryRecipient,
            ShowroomSweetsRequestNotification::class
        );

        Notification::assertSentTo(
            $admin,
            ShowroomSweetsRequestNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranch,
            ShowroomSweetsRequestNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            ShowroomSweetsRequestNotification::class
        );
    }

    #[Test]
    public function branch_cake_every_visible_transition_notifies_admin_and_related_locations(): void
    {
        Notification::fake();

        $admin =
            $this->makeAdmin(
                $this->otherBranch
            );

        $factoryUser =
            $this->makeUser(
                $this->factory,
                [
                    'showroom_cake_requests.view',
                    'showroom_cake_requests.update_status',
                ]
            );

        $branchUser =
            $this->makeUser(
                $this->branch,
                [
                    'showroom_cake_requests.view',
                    'showroom_cake_requests.create',
                    'showroom_cake_requests.update_status',
                ]
            );

        $request =
            $this->makeCakeRequest(
                'pending'
            );

        foreach ([
            ['pending', 'in_progress'],
            ['in_progress', 'ready'],
            ['ready', 'completed'],
            ['in_progress', 'cancelled'],
        ] as [$from, $to]) {
            ShowroomCakeRequestNotifier::statusChanged(
                $request,
                $from,
                $to,
                $this->actor
            );
        }

        $this->assertSame(
            4,
            Notification::sent(
                $admin,
                ShowroomCakeRequestNotification::class
            )->count()
        );

        $this->assertSame(
            4,
            Notification::sent(
                $factoryUser,
                ShowroomCakeRequestNotification::class
            )->count()
        );

        $this->assertSame(
            4,
            Notification::sent(
                $branchUser,
                ShowroomCakeRequestNotification::class
            )->count()
        );
    }

    #[Test]
    public function branch_sweets_every_visible_transition_notifies_admin_and_related_locations(): void
    {
        Notification::fake();

        $admin =
            $this->makeAdmin(
                $this->otherBranch
            );

        $factoryUser =
            $this->makeUser(
                $this->factory,
                [
                    'showroom_sweets_requests.view',
                    'showroom_sweets_requests.start',
                    'showroom_sweets_requests.ready',
                ]
            );

        $branchUser =
            $this->makeUser(
                $this->branch,
                [
                    'showroom_sweets_requests.view',
                    'showroom_sweets_requests.create',
                    'showroom_sweets_requests.receive',
                    'showroom_sweets_requests.cancel',
                ]
            );

        $request =
            $this->makeSweetsRequest(
                'pending'
            );

        foreach ([
            ['pending', 'in_progress'],
            ['in_progress', 'ready'],
            ['ready', 'completed'],
            ['in_progress', 'cancelled'],
        ] as [$from, $to]) {
            ShowroomSweetsRequestNotifier::statusChanged(
                $request,
                $from,
                $to,
                $this->actor
            );
        }

        $this->assertSame(
            4,
            Notification::sent(
                $admin,
                ShowroomSweetsRequestNotification::class
            )->count()
        );

        $this->assertSame(
            4,
            Notification::sent(
                $factoryUser,
                ShowroomSweetsRequestNotification::class
            )->count()
        );

        $this->assertSame(
            4,
            Notification::sent(
                $branchUser,
                ShowroomSweetsRequestNotification::class
            )->count()
        );
    }

    #[Test]
    public function admin_receives_notifications_even_when_admin_performs_the_action(): void
    {
        Notification::fake();

        $admin =
            $this->makeAdmin(
                $this->factory
            );

        $cake =
            $this->makeCakeRequest(
                'pending'
            );

        $sweets =
            $this->makeSweetsRequest(
                'pending'
            );

        ShowroomCakeRequestNotifier::statusChanged(
            $cake,
            'pending',
            'in_progress',
            $admin
        );

        ShowroomSweetsRequestNotifier::statusChanged(
            $sweets,
            'pending',
            'in_progress',
            $admin
        );

        Notification::assertSentTo(
            $admin,
            ShowroomCakeRequestNotification::class
        );

        Notification::assertSentTo(
            $admin,
            ShowroomSweetsRequestNotification::class
        );
    }

    #[Test]
    public function duplicate_branch_request_notifications_are_blocked_by_cache_and_database(): void
    {
        $admin =
            $this->makeAdmin(
                $this->otherBranch
            );

        $cake =
            $this->makeCakeRequest(
                'pending'
            );

        ShowroomCakeRequestNotifier::statusChanged(
            $cake,
            'pending',
            'in_progress',
            $this->actor
        );

        ShowroomCakeRequestNotifier::statusChanged(
            $cake,
            'pending',
            'in_progress',
            $this->actor
        );

        $this->assertSame(
            1,
            $admin
                ->fresh()
                ->notifications()
                ->where(
                    'type',
                    ShowroomCakeRequestNotification::class
                )
                ->count()
        );

        Cache::flush();

        ShowroomCakeRequestNotifier::statusChanged(
            $cake,
            'pending',
            'in_progress',
            $this->actor
        );

        $this->assertSame(
            1,
            $admin
                ->fresh()
                ->notifications()
                ->where(
                    'type',
                    ShowroomCakeRequestNotification::class
                )
                ->count()
        );

        $sweets =
            $this->makeSweetsRequest(
                'pending'
            );

        ShowroomSweetsRequestNotifier::statusChanged(
            $sweets,
            'pending',
            'in_progress',
            $this->actor
        );

        ShowroomSweetsRequestNotifier::statusChanged(
            $sweets,
            'pending',
            'in_progress',
            $this->actor
        );

        $this->assertSame(
            1,
            $admin
                ->fresh()
                ->notifications()
                ->where(
                    'type',
                    ShowroomSweetsRequestNotification::class
                )
                ->count()
        );

        Cache::flush();

        ShowroomSweetsRequestNotifier::statusChanged(
            $sweets,
            'pending',
            'in_progress',
            $this->actor
        );

        $this->assertSame(
            1,
            $admin
                ->fresh()
                ->notifications()
                ->where(
                    'type',
                    ShowroomSweetsRequestNotification::class
                )
                ->count()
        );
    }

    #[Test]
    public function admin_database_notifications_are_unread_and_visible_in_the_bell_endpoint_for_both_request_types(): void
    {
        $admin =
            $this->makeAdmin(
                $this->otherBranch
            );

        $cake =
            $this->makeCakeRequest(
                'pending'
            );

        $sweets =
            $this->makeSweetsRequest(
                'pending'
            );

        ShowroomCakeRequestNotifier::statusChanged(
            $cake,
            'pending',
            'in_progress',
            $this->actor
        );

        ShowroomSweetsRequestNotifier::statusChanged(
            $sweets,
            'pending',
            'in_progress',
            $this->actor
        );

        $admin->refresh();

        $this->assertSame(
            2,
            $admin
                ->unreadNotifications()
                ->count()
        );

        $types =
            $admin
                ->unreadNotifications()
                ->get()
                ->pluck('data.type')
                ->all();

        $this->assertContains(
            'showroom_cake_request_status_changed',
            $types
        );

        $this->assertContains(
            'showroom_sweets_request_status_changed',
            $types
        );

        $response =
            $this->actingAs($admin)
                ->getJson(
                    route(
                        'notifications.recent'
                    )
                )
                ->assertOk();

        $response->assertJsonCount(
            2,
            'items'
        );

        $this->actingAs($admin)
            ->getJson(
                route(
                    'notifications.count'
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'count',
                2
            );
    }

    #[Test]
    public function branch_cake_status_route_changes_state_and_dispatches_notification(): void
    {
        Notification::fake();

        $factoryActor =
            $this->makeUser(
                $this->factory,
                [
                    'showroom_cake_requests.view',
                    'showroom_cake_requests.update_status',
                ]
            );

        $branchRecipient =
            $this->makeUser(
                $this->branch,
                [
                    'showroom_cake_requests.view',
                ]
            );

        $request =
            $this->makeCakeRequest(
                'pending'
            );

        $this->actingAs($factoryActor)
            ->patch(
                route(
                    'showroom-cake-requests.status',
                    $request
                ),
                [
                    'status' =>
                        'in_progress',
                    'factory_notes' =>
                        'بدأ التنفيذ',
                ]
            )
            ->assertRedirect();

        $request->refresh();

        $this->assertSame(
            ShowroomCakeRequestStatus::InProgress,
            $request->status
        );

        $this->assertSame(
            $factoryActor->id,
            $request->handled_by
        );

        Notification::assertSentTo(
            $branchRecipient,
            ShowroomCakeRequestNotification::class
        );

        Notification::assertNotSentTo(
            $factoryActor,
            ShowroomCakeRequestNotification::class
        );
    }

    #[Test]
    public function branch_sweets_status_route_changes_state_and_dispatches_notification(): void
    {
        Notification::fake();

        $factoryActor =
            $this->makeUser(
                $this->factory,
                [
                    'showroom_sweets_requests.view',
                    'showroom_sweets_requests.start',
                ]
            );

        $branchRecipient =
            $this->makeUser(
                $this->branch,
                [
                    'showroom_sweets_requests.view',
                ]
            );

        $request =
            $this->makeSweetsRequest(
                'pending'
            );

        $this->actingAs($factoryActor)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $request
                ),
                [
                    'status' =>
                        'in_progress',
                    'factory_notes' =>
                        'بدأ التنفيذ',
                ]
            )
            ->assertRedirect();

        $request->refresh();

        $this->assertSame(
            ShowroomSweetsRequestStatus::InProgress,
            $request->status
        );

        $this->assertSame(
            $factoryActor->id,
            $request->handled_by
        );

        Notification::assertSentTo(
            $branchRecipient,
            ShowroomSweetsRequestNotification::class
        );

        Notification::assertNotSentTo(
            $factoryActor,
            ShowroomSweetsRequestNotification::class
        );
    }

    #[Test]
    public function branch_receiving_users_complete_ready_requests_for_both_flows(): void
    {
        Notification::fake();

        $cakeReceiver =
            $this->makeUser(
                $this->branch,
                [
                    'showroom_cake_requests.view',
                    'showroom_cake_requests.update_status',
                ]
            );

        $sweetsReceiver =
            $this->makeUser(
                $this->branch,
                [
                    'showroom_sweets_requests.view',
                    'showroom_sweets_requests.receive',
                ]
            );

        $cake =
            $this->makeCakeRequest(
                'ready'
            );

        $sweets =
            $this->makeSweetsRequest(
                'ready'
            );

        $this->actingAs($cakeReceiver)
            ->patch(
                route(
                    'showroom-cake-requests.status',
                    $cake
                ),
                [
                    'status' => 'completed',
                ]
            )
            ->assertRedirect();

        $this->actingAs($sweetsReceiver)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $sweets
                ),
                [
                    'status' => 'completed',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            ShowroomCakeRequestStatus::Completed,
            $cake->fresh()->status
        );

        $this->assertNotNull(
            $cake->fresh()->fulfilled_at
        );

        $this->assertSame(
            ShowroomSweetsRequestStatus::Completed,
            $sweets->fresh()->status
        );

        $this->assertSame(
            $sweetsReceiver->id,
            $sweets->fresh()->received_by
        );

        $this->assertNotNull(
            $sweets->fresh()->received_at
        );
    }

    #[Test]
    public function notification_payloads_use_only_the_simplified_statuses_and_correct_urls(): void
    {
        $admin =
            $this->makeAdmin(
                $this->branch
            );

        $cake =
            $this->makeCakeRequest(
                'ready'
            );

        $cakeData =
            (
                new ShowroomCakeRequestNotification(
                    $cake,
                    'status_changed',
                    'in_progress',
                    'ready'
                )
            )->toDatabase($admin);

        $this->assertSame(
            'in_progress',
            data_get(
                $cakeData,
                'from_status'
            )
        );

        $this->assertSame(
            'ready',
            data_get(
                $cakeData,
                'to_status'
            )
        );

        $this->assertSame(
            '/showroom-cake-requests/'
                . $cake->id,
            data_get(
                $cakeData,
                'url'
            )
        );

        $sweets =
            $this->makeSweetsRequest(
                'completed'
            );

        $sweetsData =
            (
                new ShowroomSweetsRequestNotification(
                    showroomSweetsRequest:
                        $sweets,
                    event:
                        'status_changed',
                    fromStatus:
                        'ready',
                    toStatus:
                        'completed'
                )
            )->toDatabase($admin);

        $this->assertSame(
            'ready',
            data_get(
                $sweetsData,
                'from_status'
            )
        );

        $this->assertSame(
            'completed',
            data_get(
                $sweetsData,
                'to_status'
            )
        );

        $this->assertSame(
            '/showroom-sweets-requests/'
                . $sweets->id,
            data_get(
                $sweetsData,
                'url'
            )
        );
    }

    private function makeCakeRequest(
        string $status
    ): ShowroomCakeRequest {
        return ShowroomCakeRequest::query()
            ->create([
                'request_number' =>
                    'SCR-TEST-'
                    . Str::upper(
                        Str::random(7)
                    ),
                'requesting_location_id' =>
                    $this->branch->id,
                'factory_location_id' =>
                    $this->factory->id,
                'status' => $status,
                'needed_by' =>
                    now()
                        ->addDays(2)
                        ->toDateString(),
                'created_by' =>
                    $this->actor->id,
                'submitted_at' =>
                    now(),
            ]);
    }

    private function makeSweetsRequest(
        string $status
    ): ShowroomSweetsRequest {
        return ShowroomSweetsRequest::query()
            ->create([
                'request_number' =>
                    'SSR-TEST-'
                    . Str::upper(
                        Str::random(7)
                    ),
                'requesting_location_id' =>
                    $this->branch->id,
                'factory_location_id' =>
                    $this->factory->id,
                'status' => $status,
                'needed_by' =>
                    now()
                        ->addDays(2)
                        ->toDateString(),
                'created_by' =>
                    $this->actor->id,
                'submitted_at' =>
                    now(),
            ]);
    }

    private function makeLocation(
        string $name,
        string $type
    ): Location {
        return Location::query()->create([
            'name' => $name,
            'code' =>
                'BR-'
                . Str::upper(
                    Str::random(8)
                ),
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function makeUser(
        Location $location,
        array $permissions,
        bool $active = true
    ): User {
        $employee =
            Employee::query()->create([
                'employee_number' =>
                    'BR-EMP-'
                    . Str::upper(
                        Str::random(8)
                    ),
                'full_name' =>
                    'Branch Flow User',
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

        $user =
            User::factory()->create([
                'employee_id' =>
                    $employee->id,
                'is_active' =>
                    $active,
                'must_change_password' =>
                    false,
            ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(
                Permission::findOrCreate(
                    $permission,
                    'web'
                )
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function makeAdmin(
        Location $location
    ): User {
        $user =
            $this->makeUser(
                $location,
                []
            );

        $user->assignRole(
            Role::findOrCreate(
                'Admin',
                'web'
            )
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }
}
