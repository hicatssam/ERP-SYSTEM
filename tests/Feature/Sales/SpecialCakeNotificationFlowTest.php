<?php

namespace Tests\Feature\Sales;

use App\Enums\CakeOrderStatus;
use App\Events\SpecialCakeOrderTransitioned;
use App\Models\Customer;
use App\Models\Employee;
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

class SpecialCakeNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private Location $branch;
    private Location $otherBranch;
    private Location $factory;
    private User $actor;
    private SpecialCakeOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->branch = $this->makeLocation(
            'Cake Branch',
            'branch'
        );

        $this->otherBranch = $this->makeLocation(
            'Other Branch',
            'branch'
        );

        $this->factory = $this->makeLocation(
            'Cake Factory',
            'factory'
        );

        $this->actor = $this->makeUser(
            $this->branch,
            [
                'cake_orders.view',
                'cake_orders.create',
                'cake_orders.accept',
                'cake_orders.quality_check',
                'cake_orders.complete',
                'cake_orders.cancel',
            ]
        );

        $this->order = $this->makeOrder(
            CakeOrderStatus::Pending->value
        );
    }

    #[Test]
    public function pending_stage_notifies_factory_reviewers_and_branch_receivers_only(): void
    {
        Notification::fake();

        $factoryReviewer = $this->makeUser(
            $this->factory,
            ['cake_orders.review']
        );

        $branchReceiver = $this->makeUser(
            $this->branch,
            ['cake_orders.receive']
        );

        $viewOnlyFactoryUser = $this->makeUser(
            $this->factory,
            ['cake_orders.view']
        );

        $wrongBranchReviewer = $this->makeUser(
            $this->otherBranch,
            ['cake_orders.review']
        );

        $inactiveReviewer = $this->makeUser(
            $this->factory,
            ['cake_orders.review'],
            false
        );

        $this->dispatchTransition(
            'draft',
            'pending',
            'طلب جديد للمراجعة'
        );

        Notification::assertSentTo(
            $factoryReviewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertSentTo(
            $branchReceiver,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $viewOnlyFactoryUser,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranchReviewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $inactiveReviewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            SpecialCakeOrderTransitionedNotification::class
        );

        $this->assertTransitionPayload(
            $factoryReviewer,
            'draft',
            'pending',
            'طلب جديد للمراجعة',
            'medium'
        );
    }

    #[Test]
    public function in_progress_stage_notifies_factory_production_and_branch_operational_users(): void
    {
        Notification::fake();

        $productionEmployee = $this->makeUser(
            $this->factory,
            ['cake_orders.prepare']
        );

        $branchViewer = $this->makeUser(
            $this->branch,
            ['cake_orders.view']
        );

        $dispatcherOnly = $this->makeUser(
            $this->factory,
            ['cake_orders.dispatch']
        );

        $wrongBranchViewer = $this->makeUser(
            $this->otherBranch,
            ['cake_orders.view']
        );

        $this->dispatchTransition(
            'pending',
            'in_progress',
            'بدأ تنفيذ الطلب'
        );

        Notification::assertSentTo(
            $productionEmployee,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertSentTo(
            $branchViewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $dispatcherOnly,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranchViewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            SpecialCakeOrderTransitionedNotification::class
        );

        $this->assertTransitionPayload(
            $productionEmployee,
            'pending',
            'in_progress',
            'بدأ تنفيذ الطلب',
            'medium'
        );
    }

    #[Test]
    public function ready_stage_notifies_factory_dispatch_and_branch_receiving_users(): void
    {
        Notification::fake();

        $factoryDispatcher = $this->makeUser(
            $this->factory,
            ['cake_orders.dispatch']
        );

        $branchReceiver = $this->makeUser(
            $this->branch,
            ['cake_orders.receive']
        );

        $factoryPreparer = $this->makeUser(
            $this->factory,
            ['cake_orders.prepare']
        );

        $wrongBranchReceiver = $this->makeUser(
            $this->otherBranch,
            ['cake_orders.receive']
        );

        $this->dispatchTransition(
            'in_progress',
            'ready',
            'الكيك جاهز للاستلام'
        );

        Notification::assertSentTo(
            $factoryDispatcher,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertSentTo(
            $branchReceiver,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $factoryPreparer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranchReceiver,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            SpecialCakeOrderTransitionedNotification::class
        );

        $this->assertTransitionPayload(
            $branchReceiver,
            'in_progress',
            'ready',
            'الكيك جاهز للاستلام',
            'medium'
        );
    }

    #[Test]
    public function completed_stage_notifies_branch_users_and_factory_managers_only(): void
    {
        Notification::fake();

        $branchViewer = $this->makeUser(
            $this->branch,
            ['cake_orders.view']
        );

        $factoryManager = $this->makeUser(
            $this->factory,
            ['cake_orders.manage']
        );

        $factoryDispatcher = $this->makeUser(
            $this->factory,
            ['cake_orders.dispatch']
        );

        $wrongBranchViewer = $this->makeUser(
            $this->otherBranch,
            ['cake_orders.view']
        );

        $this->dispatchTransition(
            'ready',
            'completed',
            'تم تسليم الطلب للعميل'
        );

        Notification::assertSentTo(
            $branchViewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertSentTo(
            $factoryManager,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $factoryDispatcher,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranchViewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            SpecialCakeOrderTransitionedNotification::class
        );

        $this->assertTransitionPayload(
            $branchViewer,
            'ready',
            'completed',
            'تم تسليم الطلب للعميل',
            'medium'
        );
    }

    #[Test]
    public function cancelled_stage_notifies_branch_and_factory_cancellation_stakeholders(): void
    {
        Notification::fake();

        $branchCreator = $this->makeUser(
            $this->branch,
            ['cake_orders.create']
        );

        $factoryReviewer = $this->makeUser(
            $this->factory,
            ['cake_orders.review']
        );

        $branchReceiverOnly = $this->makeUser(
            $this->branch,
            ['cake_orders.receive']
        );

        $wrongBranchCreator = $this->makeUser(
            $this->otherBranch,
            ['cake_orders.create']
        );

        $this->dispatchTransition(
            'in_progress',
            'cancelled',
            'العميل ألغى الطلب'
        );

        Notification::assertSentTo(
            $branchCreator,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertSentTo(
            $factoryReviewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $branchReceiverOnly,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranchCreator,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            SpecialCakeOrderTransitionedNotification::class
        );

        $this->assertTransitionPayload(
            $branchCreator,
            'in_progress',
            'cancelled',
            'العميل ألغى الطلب',
            'high'
        );
    }

    #[Test]
    public function global_factory_manager_receives_every_cake_status_notification_without_location_membership(): void
    {
        Notification::fake();

        $globalManager = $this->makeUser(
            $this->otherBranch,
            [],
            true,
            'Factory Manager'
        );

        foreach ([
            ['draft', 'pending'],
            ['pending', 'in_progress'],
            ['in_progress', 'ready'],
            ['ready', 'completed'],
            ['pending', 'cancelled'],
        ] as [$from, $to]) {
            $this->dispatchTransition(
                $from,
                $to,
                'اختبار مدير المصنع'
            );
        }

        $this->assertSame(
            5,
            Notification::sent(
                $globalManager,
                SpecialCakeOrderTransitionedNotification::class
            )->count()
        );
    }

    #[Test]
    public function cache_gate_prevents_duplicate_transition_notifications(): void
    {
        Notification::fake();

        $recipient = $this->makeUser(
            $this->factory,
            ['cake_orders.review']
        );

        $this->dispatchTransition(
            'draft',
            'pending',
            'نفس الحدث'
        );

        $this->dispatchTransition(
            'draft',
            'pending',
            'نفس الحدث'
        );

        $this->assertSame(
            1,
            Notification::sent(
                $recipient,
                SpecialCakeOrderTransitionedNotification::class
            )->count()
        );
    }

    #[Test]
    public function database_fallback_prevents_duplicate_after_cache_is_cleared(): void
    {
        $recipient = $this->makeUser(
            $this->factory,
            ['cake_orders.review']
        );

        $this->dispatchTransition(
            'draft',
            'pending',
            'إشعار محفوظ بقاعدة البيانات'
        );

        $this->assertSame(
            1,
            $recipient->notifications()->count()
        );

        $stored = $recipient
            ->notifications()
            ->first();

        $this->assertNull(
            $stored->read_at
        );

        $this->assertSame(
            'cake_order_transitioned',
            data_get(
                $stored->data,
                'type'
            )
        );

        $this->assertSame(
            '/cake-orders/' . $this->order->id,
            data_get(
                $stored->data,
                'url'
            )
        );

        Cache::flush();

        $this->dispatchTransition(
            'draft',
            'pending',
            'إشعار محفوظ بقاعدة البيانات'
        );

        $this->assertSame(
            1,
            $recipient
                ->fresh()
                ->notifications()
                ->count()
        );
    }

    #[Test]
    public function creation_stage_transition_from_draft_to_pending_dispatches_review_notification(): void
    {
        Notification::fake();

        $factoryReviewer = $this->makeUser(
            $this->factory,
            ['cake_orders.review']
        );

        $this->order->update([
            'status' =>
                CakeOrderStatus::Draft,
        ]);

        app(
            SpecialCakeStatusTransitionService::class
        )->transition(
            $this->order,
            CakeOrderStatus::Pending->value,
            $this->actor,
            'تم إنشاء الطلب ووضعه قيد المراجعة.'
        );

        $this->order->refresh();

        $this->assertSame(
            CakeOrderStatus::Pending,
            $this->order->status
        );

        $this->assertDatabaseHas(
            'cake_order_status_histories',
            [
                'special_cake_order_id' =>
                    $this->order->id,
                'from_status' => 'draft',
                'to_status' => 'pending',
                'changed_by' =>
                    $this->actor->id,
            ]
        );

        Notification::assertSentTo(
            $factoryReviewer,
            SpecialCakeOrderTransitionedNotification::class
        );

        $this->assertTransitionPayload(
            $factoryReviewer,
            'draft',
            'pending',
            'تم إنشاء الطلب ووضعه قيد المراجعة.',
            'medium'
        );
    }

    #[Test]
    public function real_service_transition_persists_exactly_one_database_notification_per_recipient(): void
    {
        $recipient = $this->makeUser(
            $this->factory,
            ['cake_orders.prepare']
        );

        $this->order->update([
            'status' =>
                CakeOrderStatus::Pending,
        ]);

        app(
            SpecialCakeStatusTransitionService::class
        )->transition(
            $this->order,
            CakeOrderStatus::InProgress->value,
            $this->actor,
            'تنفيذ فعلي'
        );

        $notifications =
            $recipient
                ->fresh()
                ->notifications()
                ->get();

        $this->assertCount(
            1,
            $notifications,
            'يجب أن ينتج انتقال الحالة إشعار قاعدة بيانات واحدًا فقط لكل مستلم.'
        );

        $stored = $notifications->first();

        $this->assertSame(
            SpecialCakeOrderTransitionedNotification::class,
            $stored->type
        );

        $this->assertSame(
            'cake_order_transitioned',
            data_get(
                $stored->data,
                'type'
            )
        );

        $this->assertSame(
            'pending',
            data_get(
                $stored->data,
                'from_status'
            )
        );

        $this->assertSame(
            'in_progress',
            data_get(
                $stored->data,
                'to_status'
            )
        );
    }

    #[Test]
    public function actual_transition_service_updates_status_history_and_dispatches_notification(): void
    {
        Notification::fake();

        $recipient = $this->makeUser(
            $this->factory,
            ['cake_orders.prepare']
        );

        $this->order->update([
            'status' =>
                CakeOrderStatus::Pending,
        ]);

        app(
            SpecialCakeStatusTransitionService::class
        )->transition(
            $this->order,
            CakeOrderStatus::InProgress->value,
            $this->actor,
            'بدأ المصنع التنفيذ'
        );

        $this->order->refresh();

        $this->assertSame(
            CakeOrderStatus::InProgress,
            $this->order->status
        );

        $this->assertDatabaseHas(
            'cake_order_status_histories',
            [
                'special_cake_order_id' =>
                    $this->order->id,
                'from_status' => 'pending',
                'to_status' => 'in_progress',
                'changed_by' =>
                    $this->actor->id,
                'note' =>
                    'بدأ المصنع التنفيذ',
            ]
        );

        Notification::assertSentTo(
            $recipient,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertNotSentTo(
            $this->actor,
            SpecialCakeOrderTransitionedNotification::class
        );
    }

    #[Test]
    public function cancellation_from_every_legal_stage_records_reason_and_notifies(): void
    {
        Notification::fake();

        $branchRecipient = $this->makeUser(
            $this->branch,
            ['cake_orders.create']
        );

        $factoryRecipient = $this->makeUser(
            $this->factory,
            ['cake_orders.review']
        );

        foreach (
            [
                'draft',
                'pending',
                'in_progress',
                'ready',
            ]
            as $index => $fromStatus
        ) {
            $order = $this->makeOrder(
                $fromStatus,
                'CK-CANCEL-' . $index
            );

            app(
                SpecialCakeStatusTransitionService::class
            )->transition(
                $order,
                'cancelled',
                $this->actor,
                'سبب إلغاء ' . $fromStatus
            );

            $order->refresh();

            $this->assertSame(
                CakeOrderStatus::Cancelled,
                $order->status
            );

            $this->assertSame(
                $this->actor->id,
                $order->cancelled_by
            );

            $this->assertSame(
                'سبب إلغاء ' . $fromStatus,
                $order->cancellation_reason
            );
        }

        $this->assertSame(
            4,
            Notification::sent(
                $branchRecipient,
                SpecialCakeOrderTransitionedNotification::class
            )->count()
        );

        $this->assertSame(
            4,
            Notification::sent(
                $factoryRecipient,
                SpecialCakeOrderTransitionedNotification::class
            )->count()
        );
    }

    #[Test]
    public function comment_notifies_related_branch_factory_and_global_users_but_not_actor_or_other_branch(): void
    {
        Notification::fake();

        $commenter = $this->makeUser(
            $this->branch,
            ['cake_orders.view']
        );

        $branchRecipient = $this->makeUser(
            $this->branch,
            ['cake_orders.view']
        );

        $factoryRecipient = $this->makeUser(
            $this->factory,
            ['cake_orders.manage']
        );

        $globalRecipient = $this->makeUser(
            $this->otherBranch,
            ['cake_orders.view_all']
        );

        $otherBranchUser = $this->makeUser(
            $this->otherBranch,
            ['cake_orders.view']
        );

        $inactiveUser = $this->makeUser(
            $this->branch,
            ['cake_orders.view'],
            false
        );

        $adminRecipient = $this->makeAdmin(
            $this->otherBranch
        );

        $this->actingAs($commenter)
            ->post(
                route(
                    'cake-orders.comment',
                    $this->order
                ),
                [
                    'comment' =>
                        'العميل طلب تعديل الكتابة على الكيك.',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'cake_order_comments',
            [
                'special_cake_order_id' =>
                    $this->order->id,
                'user_id' =>
                    $commenter->id,
                'comment' =>
                    'العميل طلب تعديل الكتابة على الكيك.',
            ]
        );

        foreach ([
            $branchRecipient,
            $factoryRecipient,
            $globalRecipient,
            $adminRecipient,
        ] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                SpecialCakeOrderActivityNotification::class
            );
        }

        foreach ([
            $commenter,
            $otherBranchUser,
            $inactiveUser,
        ] as $notRecipient) {
            Notification::assertNotSentTo(
                $notRecipient,
                SpecialCakeOrderActivityNotification::class
            );
        }

        Notification::assertSentTo(
            $branchRecipient,
            SpecialCakeOrderActivityNotification::class,
            function (
                SpecialCakeOrderActivityNotification $notification
            ) use ($branchRecipient): bool {
                $data =
                    $notification->toDatabase(
                        $branchRecipient
                    );

                return data_get(
                    $data,
                    'activity'
                ) === 'comment'
                    && data_get(
                        $data,
                        'cake_order_id'
                    ) === $this->order->id
                    && str_contains(
                        (string) data_get(
                            $data,
                            'message'
                        ),
                        'العميل طلب تعديل الكتابة'
                    )
                    && data_get(
                        $data,
                        'url'
                    ) === '/cake-orders/'
                        . $this->order->id;
            }
        );
    }

    #[Test]
    public function attachment_notifies_related_users_and_stores_file_without_notifying_uploader(): void
    {
        Notification::fake();

        $uploader = $this->makeUser(
            $this->branch,
            ['cake_orders.view']
        );

        $branchRecipient = $this->makeUser(
            $this->branch,
            ['cake_orders.view']
        );

        $factoryRecipient = $this->makeUser(
            $this->factory,
            ['cake_orders.manage']
        );

        $adminRecipient = $this->makeAdmin(
            $this->otherBranch
        );

        $file = UploadedFile::fake()
            ->create(
                'final-cake.jpg',
                24,
                'image/jpeg'
            );

        $this->actingAs($uploader)
            ->post(
                route(
                    'cake-orders.attachment',
                    $this->order
                ),
                [
                    'attachment_type' =>
                        'final_cake_image',
                    'file' => $file,
                ]
            )
            ->assertRedirect();

        $attachment =
            $this->order
                ->attachments()
                ->sole();

        $this->assertSame(
            'final_cake_image',
            $attachment->attachment_type
        );

        $this->assertSame(
            'final-cake.jpg',
            $attachment->original_name
        );

        Storage::disk('public')
            ->assertExists(
                $attachment->file_path
            );

        Notification::assertSentTo(
            $branchRecipient,
            SpecialCakeOrderActivityNotification::class
        );

        Notification::assertSentTo(
            $factoryRecipient,
            SpecialCakeOrderActivityNotification::class
        );

        Notification::assertSentTo(
            $adminRecipient,
            SpecialCakeOrderActivityNotification::class
        );

        Notification::assertNotSentTo(
            $uploader,
            SpecialCakeOrderActivityNotification::class
        );

        Notification::assertSentTo(
            $factoryRecipient,
            SpecialCakeOrderActivityNotification::class,
            function (
                SpecialCakeOrderActivityNotification $notification
            ) use ($factoryRecipient): bool {
                $data =
                    $notification->toDatabase(
                        $factoryRecipient
                    );

                return data_get(
                    $data,
                    'activity'
                ) === 'attachment'
                    && str_contains(
                        (string) data_get(
                            $data,
                            'message'
                        ),
                        'final-cake.jpg'
                    )
                    && data_get(
                        $data,
                        'url'
                    ) === '/cake-orders/'
                        . $this->order->id;
            }
        );
    }

    #[Test]
    public function admin_receives_every_visible_cake_transition_even_from_another_location(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin(
            $this->otherBranch
        );

        foreach ([
            ['draft', 'pending'],
            ['pending', 'in_progress'],
            ['in_progress', 'ready'],
            ['ready', 'completed'],
            ['in_progress', 'cancelled'],
        ] as [$from, $to]) {
            $this->dispatchTransition(
                $from,
                $to,
                'Admin global feed'
            );
        }

        $this->assertSame(
            5,
            Notification::sent(
                $admin,
                SpecialCakeOrderTransitionedNotification::class
            )->count()
        );
    }

    #[Test]
    public function admin_receives_cake_transition_even_when_admin_performs_the_action(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin(
            $this->otherBranch
        );

        $order = $this->makeOrder(
            CakeOrderStatus::Pending->value,
            'CK-ADMIN-SELF'
        );

        SpecialCakeOrderTransitioned::dispatch(
            $order->fresh(),
            'pending',
            'in_progress',
            $admin,
            'نفذها مدير النظام'
        );

        Notification::assertSentTo(
            $admin,
            SpecialCakeOrderTransitionedNotification::class
        );

        Notification::assertSentTo(
            $admin,
            SpecialCakeOrderTransitionedNotification::class,
            function (
                SpecialCakeOrderTransitionedNotification $notification
            ) use ($admin, $order): bool {
                $data =
                    $notification->toDatabase(
                        $admin
                    );

                return data_get(
                    $data,
                    'order_id'
                ) === $order->id
                    && data_get(
                        $data,
                        'from_status'
                    ) === 'pending'
                    && data_get(
                        $data,
                        'to_status'
                    ) === 'in_progress'
                    && data_get(
                        $data,
                        'note'
                    ) === 'نفذها مدير النظام';
            }
        );
    }

    #[Test]
    public function admin_receives_own_comment_and_attachment_activity_notifications(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin(
            $this->branch
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'cake-orders.comment',
                    $this->order
                ),
                [
                    'comment' =>
                        'ملاحظة مدير النظام',
                ]
            )
            ->assertRedirect();

        Notification::assertSentTo(
            $admin,
            SpecialCakeOrderActivityNotification::class,
            function (
                SpecialCakeOrderActivityNotification $notification
            ) use ($admin): bool {
                return data_get(
                    $notification->toDatabase($admin),
                    'activity'
                ) === 'comment';
            }
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'cake-orders.attachment',
                    $this->order
                ),
                [
                    'attachment_type' =>
                        'other',
                    'file' =>
                        UploadedFile::fake()->create(
                            'admin-note.pdf',
                            12,
                            'application/pdf'
                        ),
                ]
            )
            ->assertRedirect();

        $activities = Notification::sent(
            $admin,
            SpecialCakeOrderActivityNotification::class
        );

        $this->assertSame(
            2,
            $activities->count()
        );

        $this->assertTrue(
            $activities->contains(
                function (
                    SpecialCakeOrderActivityNotification $notification
                ) use ($admin): bool {
                    return data_get(
                        $notification->toDatabase($admin),
                        'activity'
                    ) === 'attachment';
                }
            )
        );
    }

    #[Test]
    public function transition_payload_has_correct_labels_locations_note_url_and_priority_for_every_visible_stage(): void
    {
        $cases = [
            [
                'draft',
                'pending',
                'مسودة داخلية',
                'قيد المراجعة',
                'medium',
            ],
            [
                'pending',
                'in_progress',
                'قيد المراجعة',
                'قيد التنفيذ',
                'medium',
            ],
            [
                'in_progress',
                'ready',
                'قيد التنفيذ',
                'جاهز للاستلام',
                'medium',
            ],
            [
                'ready',
                'completed',
                'جاهز للاستلام',
                'مكتمل',
                'medium',
            ],
            [
                'pending',
                'cancelled',
                'قيد المراجعة',
                'ملغي',
                'high',
            ],
        ];

        foreach (
            $cases
            as [
                $from,
                $to,
                $fromLabel,
                $toLabel,
                $priority,
            ]
        ) {
            $notification =
                new SpecialCakeOrderTransitionedNotification(
                    $this->order,
                    $from,
                    $to,
                    'ملاحظة اختبار'
                );

            $data =
                $notification->toDatabase(
                    $this->actor
                );

            $this->assertSame(
                $this->order->id,
                data_get(
                    $data,
                    'order_id'
                )
            );

            $this->assertSame(
                $this->order->order_number,
                data_get(
                    $data,
                    'order_number'
                )
            );

            $this->assertSame(
                $this->branch->id,
                data_get(
                    $data,
                    'origin_branch_id'
                )
            );

            $this->assertSame(
                $this->branch->name,
                data_get(
                    $data,
                    'origin_branch_name'
                )
            );

            $this->assertSame(
                $this->factory->id,
                data_get(
                    $data,
                    'factory_location_id'
                )
            );

            $this->assertSame(
                $this->factory->name,
                data_get(
                    $data,
                    'factory_name'
                )
            );

            $this->assertSame(
                $from,
                data_get(
                    $data,
                    'from_status'
                )
            );

            $this->assertSame(
                $to,
                data_get(
                    $data,
                    'to_status'
                )
            );

            $this->assertSame(
                'ملاحظة اختبار',
                data_get(
                    $data,
                    'note'
                )
            );

            $this->assertSame(
                '/cake-orders/'
                    . $this->order->id,
                data_get(
                    $data,
                    'url'
                )
            );

            $this->assertSame(
                $priority,
                data_get(
                    $data,
                    'priority'
                )
            );

            $this->assertSame(
                'cake_order_transitioned',
                data_get(
                    $data,
                    'type'
                )
            );

            $this->assertSame(
                'cake_transition_'
                    . $this->order->id
                    . '_'
                    . $from
                    . '_'
                    . $to,
                data_get(
                    $data,
                    'fingerprint'
                )
            );

            $this->assertSame(
                'تحديث طلب كيك #'
                    . $this->order->order_number,
                data_get(
                    $data,
                    'title'
                )
            );

            $message =
                (string) data_get(
                    $data,
                    'message'
                );

            $this->assertStringContainsString(
                '[' . $fromLabel . ']',
                $message
            );

            $this->assertStringContainsString(
                '[' . $toLabel . ']',
                $message
            );
        }
    }

    private function assertTransitionPayload(
        User $recipient,
        string $from,
        string $to,
        string $note,
        string $priority
    ): void {
        Notification::assertSentTo(
            $recipient,
            SpecialCakeOrderTransitionedNotification::class,
            function (
                SpecialCakeOrderTransitionedNotification $notification
            ) use (
                $recipient,
                $from,
                $to,
                $note,
                $priority
            ): bool {
                $data =
                    $notification->toDatabase(
                        $recipient
                    );

                return data_get(
                    $data,
                    'from_status'
                ) === $from
                    && data_get(
                        $data,
                        'to_status'
                    ) === $to
                    && data_get(
                        $data,
                        'note'
                    ) === $note
                    && data_get(
                        $data,
                        'priority'
                    ) === $priority
                    && data_get(
                        $data,
                        'origin_branch_id'
                    ) === $this->branch->id
                    && data_get(
                        $data,
                        'factory_location_id'
                    ) === $this->factory->id
                    && data_get(
                        $data,
                        'url'
                    ) === '/cake-orders/'
                        . $this->order->id;
            }
        );
    }

    private function dispatchTransition(
        string $from,
        string $to,
        ?string $note = null
    ): void {
        $this->order->update([
            'status' => $to,
        ]);

        SpecialCakeOrderTransitioned::dispatch(
            $this->order->fresh(),
            $from,
            $to,
            $this->actor,
            $note
        );
    }

    private function makeLocation(
        string $name,
        string $type
    ): Location {
        return Location::query()->create([
            'name' => $name,
            'code' =>
                'CK-'
                . Str::upper(
                    Str::random(8)
                ),
            'type' => $type,
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
                    'CK-EMP-'
                    . Str::upper(
                        Str::random(8)
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

    private function makeUser(
        Location $location,
        array $permissions = [],
        bool $active = true,
        ?string $role = null
    ): User {
        $employee =
            $this->makeEmployee(
                $location,
                'Cake User '
                    . Str::random(5)
            );

        $user =
            User::factory()->create([
                'employee_id' =>
                    $employee->id,
                'is_active' => $active,
                'must_change_password' =>
                    false,
            ]);

        $this->givePermissions(
            $user,
            $permissions
        );

        if ($role) {
            $user->assignRole(
                Role::findOrCreate(
                    $role,
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
        return $this->makeUser(
            $location,
            [],
            true,
            'Admin'
        );
    }

    private function givePermissions(
        User $user,
        array $permissions
    ): void {
        foreach (
            array_unique($permissions)
            as $permission
        ) {
            $user->givePermissionTo(
                Permission::findOrCreate(
                    $permission,
                    'web'
                )
            );
        }
    }

    private function makeOrder(
        string $status,
        ?string $orderNumber = null
    ): SpecialCakeOrder {
        static $counter = 0;

        $counter++;

        $customer =
            Customer::query()->create([
                'location_id' =>
                    $this->branch->id,
                'customer_type' =>
                    Customer::TYPE_INDIVIDUAL,
                'scope' =>
                    Customer::SCOPE_BRANCH,
                'name' =>
                    'Cake Customer '
                    . $counter,
                'phone' =>
                    '059'
                    . str_pad(
                        (string) $counter,
                        7,
                        '0',
                        STR_PAD_LEFT
                    ),
                'allow_credit' => false,
                'billing_cycle' =>
                    'immediate',
                'payment_terms_days' =>
                    0,
            ]);

        return SpecialCakeOrder::query()
            ->create([
                'order_number' =>
                    $orderNumber
                    ?? 'CK-TEST-'
                        . Str::upper(
                            Str::random(8)
                        ),
                'customer_id' =>
                    $customer->id,
                'origin_branch_id' =>
                    $this->branch->id,
                'factory_location_id' =>
                    $this->factory->id,
                'required_date' =>
                    now()
                        ->addDays(2)
                        ->toDateString(),
                'cake_type' =>
                    'chocolate',
                'cake_size' =>
                    'medium',
                'total_price' =>
                    150,
                'discount_type' =>
                    'none',
                'discount_value' =>
                    0,
                'discount_amount' =>
                    0,
                'net_price' =>
                    150,
                'status' => $status,
                'payment_status' =>
                    'payment_pending',
                'payment_arrangement' =>
                    'pay_on_pickup',
                'image_cover_type' =>
                    'none',
                'created_by' =>
                    $this->actor->id,
            ]);
    }
}
