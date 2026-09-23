<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Location;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\SpecialCakeOrderActivityNotification;
use App\Notifications\SpecialCakeOrderTransitionedNotification;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpecialCakeWorkflowAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function pending_factory_review_notifies_factory_reviewer_only(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();
        $actor = $this->admin();
        $factoryReviewer = $this->userAt($factory, [
            'cake_orders.view',
            'cake_orders.review',
        ]);
        $branchViewer = $this->userAt($branch, [
            'cake_orders.view',
        ]);

        $order = $this->cakeOrder($branch, $factory, $actor);

        app(SpecialCakeStatusTransitionService::class)->transition(
            $order,
            'pending_factory_review',
            $actor,
            'اختبار إرسال الطلب للمصنع'
        );

        $order->refresh();

        $this->assertSame('pending_factory_review', $order->status->value);

        $this->assertDatabaseHas('cake_order_status_histories', [
            'special_cake_order_id' => $order->id,
            'from_status' => 'draft',
            'to_status' => 'pending_factory_review',
            'changed_by' => $actor->id,
        ]);

        Notification::assertSentTo(
            $factoryReviewer,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryReviewer)['to_status']
                === 'pending_factory_review'
        );

        Notification::assertNotSentTo(
            $branchViewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $actor,
            SpecialCakeOrderTransitionedNotification::class
        );
    }

    #[Test]
    public function modification_request_returns_notification_to_branch_editor(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();
        $actor = $this->admin();

        $branchEditor = $this->userAt($branch, [
            'cake_orders.view',
            'cake_orders.edit',
        ]);

        $factoryReviewer = $this->userAt($factory, [
            'cake_orders.view',
            'cake_orders.review',
        ]);

        $order = $this->cakeOrder(
            $branch,
            $factory,
            $actor,
            'pending_factory_review'
        );

        app(SpecialCakeStatusTransitionService::class)->transition(
            $order,
            'modification_requested',
            $actor,
            'يرجى تعديل النص على الكيك'
        );

        Notification::assertSentTo(
            $branchEditor,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($branchEditor)['to_status']
                === 'modification_requested'
        );

        Notification::assertNotSentTo(
            $factoryReviewer,
            SpecialCakeOrderTransitionedNotification::class
        );
    }

    #[Test]
    public function complete_special_cake_production_lifecycle_is_persisted_and_notified(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();
        $actor = $this->admin();

        $factoryManager = $this->userAt($factory, [
            'cake_orders.view',
            'cake_orders.manage',
        ]);

        $branchManager = $this->userAt($branch, [
            'cake_orders.view',
            'cake_orders.manage',
        ]);

        $order = $this->cakeOrder($branch, $factory, $actor);

        $sequence = [
            'pending_factory_review',
            'accepted',
            'scheduled',
            'in_preparation',
            'decorating',
            'quality_check',
            'ready',
            'sent_to_branch',
            'received_by_branch',
            'ready_for_customer',
            'completed',
        ];

        $service = app(SpecialCakeStatusTransitionService::class);

        foreach ($sequence as $status) {
            $service->transition(
                $order->fresh(),
                $status,
                $actor,
                'اختبار المرحلة: ' . $status
            );
        }

        $order->refresh();

        $this->assertSame('completed', $order->status->value);
        $this->assertNotNull($order->scheduled_at);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(
            count($sequence),
            $order->statusHistories()->count()
        );

        Notification::assertSentTo(
            $factoryManager,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryManager)['to_status']
                === 'pending_factory_review'
        );

        Notification::assertNotSentTo(
            $branchManager,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($branchManager)['to_status']
                === 'pending_factory_review'
        );

        Notification::assertSentTo(
            $branchManager,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($branchManager)['to_status']
                === 'sent_to_branch'
        );

        Notification::assertNotSentTo(
            $factoryManager,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryManager)['to_status']
                === 'sent_to_branch'
        );

        Notification::assertSentTo(
            $branchManager,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($branchManager)['to_status']
                === 'completed'
        );

        $lastHistory = $order->statusHistories()
            ->where('to_status', 'completed')
            ->first();

        $this->assertNotNull($lastHistory);
        $this->assertSame('ready_for_customer', $lastHistory->from_status);
    }

    #[Test]
    public function cancelling_special_cake_records_actor_time_and_reason(): void
    {
        Notification::fake();

        [$branch, $factory] = $this->locations();
        $actor = $this->admin();

        $order = $this->cakeOrder($branch, $factory, $actor);

        app(SpecialCakeStatusTransitionService::class)->transition(
            $order,
            'cancelled',
            $actor,
            'العميل ألغى الطلب'
        );

        $order->refresh();

        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame($actor->id, $order->cancelled_by);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(
            'العميل ألغى الطلب',
            $order->cancellation_reason
        );
    }

    #[Test]
    public function another_branch_cannot_comment_or_upload_to_special_cake_order(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$branch, $factory] = $this->locations();
        $otherBranch = Location::query()->create([
            'name' => 'فرع آخر',
            'code' => 'BR-OTHER-' . Str::upper(Str::random(4)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $creator = $this->admin();
        $order = $this->cakeOrder($branch, $factory, $creator);

        $outsider = $this->userAt($otherBranch, [
            'cake_orders.view',
        ]);

        $this->actingAs($outsider)
            ->post(route('cake-orders.comment', $order), [
                'comment' => 'يجب ألا تُحفظ هذه الملاحظة',
            ])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('cake-orders.attachment', $order), [
                'attachment_type' => 'other',
                'file' => UploadedFile::fake()->image('forbidden.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('cake_order_comments', [
            'special_cake_order_id' => $order->id,
            'comment' => 'يجب ألا تُحفظ هذه الملاحظة',
        ]);

        $this->assertSame(0, $order->attachments()->count());
    }

    #[Test]
    public function cake_comment_and_attachment_notify_other_authorized_participants(): void
    {
        Notification::fake();
        Storage::fake('public');

        [$branch, $factory] = $this->locations();
        $creator = $this->admin();

        $branchUser = $this->userAt($branch, [
            'cake_orders.view',
        ]);

        $factoryUser = $this->userAt($factory, [
            'cake_orders.view',
        ]);

        $order = $this->cakeOrder(
            $branch,
            $factory,
            $creator,
            'pending_factory_review'
        );

        $this->actingAs($branchUser)
            ->from(route('cake-orders.show', $order))
            ->post(route('cake-orders.comment', $order), [
                'comment' => 'يرجى تثبيت لون الزينة كما في الصورة.',
            ])
            ->assertRedirect(route('cake-orders.show', $order));

        $this->assertDatabaseHas('cake_order_comments', [
            'special_cake_order_id' => $order->id,
            'user_id' => $branchUser->id,
            'comment' => 'يرجى تثبيت لون الزينة كما في الصورة.',
        ]);

        Notification::assertSentTo(
            $factoryUser,
            SpecialCakeOrderActivityNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryUser)['activity']
                === 'comment'
        );

        Notification::assertNotSentTo(
            $branchUser,
            SpecialCakeOrderActivityNotification::class
        );

        $this->actingAs($branchUser)
            ->from(route('cake-orders.show', $order))
            ->post(route('cake-orders.attachment', $order), [
                'attachment_type' => 'customer_design',
                'file' => UploadedFile::fake()->image('customer-design.jpg'),
            ])
            ->assertRedirect(route('cake-orders.show', $order));

        $this->assertSame(1, $order->attachments()->count());

        Notification::assertSentTo(
            $factoryUser,
            SpecialCakeOrderActivityNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryUser)['activity']
                === 'attachment'
        );
    }

    private function locations(): array
    {
        $branch = Location::query()->create([
            'name' => 'فرع اختبار الكيك',
            'code' => 'CAKE-BR-' . Str::upper(Str::random(4)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'مصنع اختبار الكيك',
            'code' => 'CAKE-FA-' . Str::upper(Str::random(4)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        return [$branch, $factory];
    }

    private function admin(): User
    {
        $user = User::factory()->create();

        $role = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function userAt(Location $location, array $permissions): User
    {
        $user = User::factory()->create();

        $user->employee->locations()->attach($location->id, [
            'is_primary' => true,
        ]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::findOrCreate(
                $permissionName,
                'web'
            );

            $user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function cakeOrder(
        Location $branch,
        Location $factory,
        User $creator,
        string $status = 'draft'
    ): SpecialCakeOrder {
        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'عميل اختبار الكيك',
            'phone' => '059' . random_int(1000000, 9999999),
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        return SpecialCakeOrder::query()->create([
            'order_number' => 'CKO-TEST-' . Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'origin_branch_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'required_date' => now()->addDays(3)->toDateString(),
            'required_time' => '15:00',
            'cake_type' => 'Birthday',
            'cake_size' => 'Medium',
            'total_price' => 100,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_price' => 100,
            'status' => $status,
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'created_by' => $creator->id,
        ]);
    }
}
