<?php

namespace Tests\Feature\Sales;

use App\Models\Location;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Notifications\ShowroomSweetsRequestNotification;
use App\Services\Notifications\ShowroomSweetsRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShowroomSweetsWorkflowAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function new_showroom_sweets_request_notifies_factory_task_owner(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $factoryStarter = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.start',
        ]);

        $branchViewer = $this->userAt($branch, [
            'showroom_sweets_requests.view',
        ]);

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);

        $request = $this->showroomRequest(
            $branch,
            $factory,
            $creator
        );

        ShowroomSweetsRequestNotifier::requestCreated(
            $request->fresh()
        );

        Notification::assertSentTo(
            $factoryStarter,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryStarter)['type']
                === 'showroom_sweets_request_created'
        );

        Notification::assertNotSentTo(
            $branchViewer,
            ShowroomSweetsRequestNotification::class
        );
    }

    #[Test]
    public function showroom_sweets_happy_path_moves_task_between_factory_and_branch(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);

        $starter = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.start',
        ]);

        $readyUser = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.ready',
        ]);

        $dispatcher = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.dispatch',
        ]);

        $factoryObserver = $this->userAt($factory, [
            'showroom_sweets_requests.view',
        ]);

        $branchReceiver = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.receive',
        ]);

        $showroomRequest = $this->showroomRequest(
            $branch,
            $factory,
            $creator
        );

        $showUrl = route(
            'showroom-sweets-requests.show',
            $showroomRequest
        );

        $this->actingAs($starter)
            ->from($showUrl)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $showroomRequest
                ),
                [
                    'status' => 'in_progress',
                    'factory_notes' => 'بدأ التجهيز',
                ]
            )
            ->assertRedirect($showUrl);

        $showroomRequest->refresh();

        $this->assertSame(
            'in_progress',
            $showroomRequest->status->value
        );
        $this->assertSame(
            $starter->id,
            $showroomRequest->handled_by
        );

        Notification::assertSentTo(
            $readyUser,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($readyUser)['to_status']
                === 'in_progress'
        );

        Notification::assertNotSentTo(
            $starter,
            ShowroomSweetsRequestNotification::class
        );

        $this->actingAs($readyUser)
            ->from($showUrl)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $showroomRequest
                ),
                [
                    'status' => 'ready_for_dispatch',
                ]
            )
            ->assertRedirect($showUrl);

        $showroomRequest->refresh();

        Notification::assertSentTo(
            $dispatcher,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($dispatcher)['to_status']
                === 'ready_for_dispatch'
        );

        $this->actingAs($dispatcher)
            ->from($showUrl)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $showroomRequest
                ),
                [
                    'status' => 'out_for_delivery',
                ]
            )
            ->assertRedirect($showUrl);

        $showroomRequest->refresh();

        $this->assertSame(
            $dispatcher->id,
            $showroomRequest->dispatched_by
        );
        $this->assertNotNull(
            $showroomRequest->dispatched_at
        );

        Notification::assertSentTo(
            $branchReceiver,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($branchReceiver)['to_status']
                === 'out_for_delivery'
        );

        $this->actingAs($branchReceiver)
            ->from($showUrl)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $showroomRequest
                ),
                [
                    'status' => 'received_at_branch',
                ]
            )
            ->assertRedirect($showUrl);

        $showroomRequest->refresh();

        $this->assertSame(
            'received_at_branch',
            $showroomRequest->status->value
        );
        $this->assertSame(
            $branchReceiver->id,
            $showroomRequest->received_by
        );
        $this->assertNotNull(
            $showroomRequest->received_at
        );
        $this->assertNotNull(
            $showroomRequest->fulfilled_at
        );

        Notification::assertSentTo(
            $factoryObserver,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryObserver)['to_status']
                === 'received_at_branch'
        );
    }

    #[Test]
    public function branch_can_cancel_submitted_request_with_cancel_permission_and_factory_is_notified(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.create',
        ]);

        $branchCanceller = $this->userAt($branch, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.cancel',
        ]);

        $factoryStarter = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.start',
        ]);

        $request = $this->showroomRequest(
            $branch,
            $factory,
            $creator
        );

        $this->actingAs($branchCanceller)
            ->from(route('showroom-sweets-requests.show', $request))
            ->patch(
                route(
                    'showroom-sweets-requests.cancel',
                    $request
                )
            )
            ->assertRedirect(
                route(
                    'showroom-sweets-requests.show',
                    $request
                )
            );

        $request->refresh();

        $this->assertSame(
            'cancelled',
            $request->status->value
        );

        Notification::assertSentTo(
            $factoryStarter,
            ShowroomSweetsRequestNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryStarter)['to_status']
                === 'cancelled'
        );
    }

    #[Test]
    public function factory_user_cannot_execute_branch_receive_stage(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();

        $creator = $this->userAt($branch, [
            'showroom_sweets_requests.view',
        ]);

        $factoryReceiver = $this->userAt($factory, [
            'showroom_sweets_requests.view',
            'showroom_sweets_requests.receive',
        ]);

        $request = $this->showroomRequest(
            $branch,
            $factory,
            $creator,
            'out_for_delivery'
        );

        $this->actingAs($factoryReceiver)
            ->patch(
                route(
                    'showroom-sweets-requests.status',
                    $request
                ),
                [
                    'status' => 'received_at_branch',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'out_for_delivery',
            $request->fresh()->status->value
        );
    }

    private function locations(): array
    {
        $branch = Location::query()->create([
            'name' => 'فرع اختبار الحلويات',
            'code' => 'SWT-BR-' . Str::upper(Str::random(4)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'مصنع اختبار الحلويات',
            'code' => 'SWT-FA-' . Str::upper(Str::random(4)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        return [$branch, $factory];
    }

    private function userAt(
        Location $location,
        array $permissions
    ): User {
        $user = User::factory()->create();

        $user->employee->locations()->attach(
            $location->id,
            [
                'is_primary' => true,
            ]
        );

        foreach ($permissions as $permissionName) {
            $permission = Permission::findOrCreate(
                $permissionName,
                'web'
            );

            $user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user->fresh();
    }

    private function showroomRequest(
        Location $branch,
        Location $factory,
        User $creator,
        string $status = 'submitted'
    ): ShowroomSweetsRequest {
        return ShowroomSweetsRequest::query()->create([
            'request_number' =>
                'SSR-TEST-' . Str::upper(Str::random(8)),
            'requesting_location_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'status' => $status,
            'needed_by' => now()->addDays(2)->toDateString(),
            'notes' => 'اختبار دورة طلب الحلويات',
            'created_by' => $creator->id,
            'submitted_at' => now(),
        ]);
    }
}
