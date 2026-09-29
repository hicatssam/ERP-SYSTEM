<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Location;
use App\Models\Module;
use App\Models\PaymentMethod;
use App\Models\SystemSetting;
use App\Models\User;
use App\Http\Controllers\Admin\SettingsController;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SystemNavigationPwaSettingsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Location $branch;
    private Location $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->branch = $this->makeLocation(
            'Navigation Branch'
        );

        $this->otherBranch = $this->makeLocation(
            'Other Branch'
        );
    }

    #[Test]
    public function every_static_route_used_by_main_navigation_exists(): void
    {
        $layout = file_get_contents(
            resource_path(
                'views/layouts/app.blade.php'
            )
        );

        preg_match_all(
            "/route\\('([^']+)'/",
            $layout,
            $matches
        );

        $routeNames = collect(
            $matches[1] ?? []
        )
            ->unique()
            ->values();

        $this->assertNotEmpty(
            $routeNames,
            'لم يتم العثور على روابط مسماة داخل المنيو الرئيسي.'
        );

        foreach ($routeNames as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "رابط المنيو [{$routeName}] غير مسجل في routes."
            );
        }
    }

    #[Test]
    public function settings_routes_are_denied_without_settings_permission(): void
    {
        $user = $this->makeUser(
            $this->branch
        );

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('business-profiles.index'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_open_core_settings_module_and_business_profile_pages(): void
    {
        $admin = $this->makeUser(
            $this->branch,
            ['settings.manage'],
            true,
            'Admin'
        );

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('modules.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('business-profiles.index'))
            ->assertOk();
    }

    #[Test]
    public function settings_sections_are_ordered_and_only_show_editable_fields(): void
    {
        $admin = $this->makeUser($this->branch, ['settings.manage'], true, 'Admin');

        foreach ([
            ['timezone', 'general'],
            ['business_legal_name', 'business'],
            ['business_profile_code', 'business'],
            ['client_onboarding_completed', 'onboarding'],
            ['brand_logo', 'branding'],
            ['special_cake_auto_approval', 'branding'],
            ['assistant_enabled', 'assistant'],
            ['customer_menu_title', 'customer-menu'],
            ['kds_poll_seconds', 'kitchen'],
            ['print_template', 'print_branding'],
        ] as [$key, $group]) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => '1', 'type' => 'string', 'group' => $group, 'label' => $key]
            );
        }
        SystemSetting::flushCache();

        $settings = app(SettingsController::class)->index()->getData()['settings'];
        $groups = $settings->keys()->all();

        $this->assertLessThan(array_search('branding', $groups, true), array_search('business', $groups, true));
        $this->assertLessThan(array_search('assistant', $groups, true), array_search('branding', $groups, true));
        $this->assertLessThan(array_search('customer_menu', $groups, true), array_search('kitchen', $groups, true));
        $this->assertLessThan(array_search('customer_menu', $groups, true), array_search('assistant', $groups, true));
        $this->assertTrue($settings->has('customer_menu'));
        $this->assertFalse($settings->has('customer-menu'));
        $this->assertFalse($settings->has('onboarding'));
        $this->assertFalse($settings->has('print_branding'));
        $this->assertFalse($settings['business']->contains('key', 'business_profile_code'));
        $this->assertTrue($settings['cake_orders']->contains('key', 'special_cake_auto_approval'));

        $html = $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('بيانات المنشأة')
            ->assertSee('شاشة المطبخ')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="attendance-settings-entry"'));
        $this->assertSame(1, substr_count($html, 'class="print-branding-settings-entry"'));
        $this->assertStringNotContainsString('id="setting_business_profile_code"', $html);
        $this->assertStringNotContainsString('id="setting_print_template"', $html);
    }

    #[Test]
    public function kitchen_settings_shown_in_the_main_form_can_be_saved(): void
    {
        $admin = $this->makeUser($this->branch, ['settings.manage'], true, 'Admin');
        SystemSetting::query()->updateOrCreate(
            ['key' => 'kds_poll_seconds'],
            ['value' => '3', 'type' => 'integer', 'group' => 'kitchen', 'label' => 'فترة تحديث شاشة المطبخ']
        );

        $this->actingAs($admin)
            ->post(route('settings.update'), [
                'kds_poll_seconds' => 5,
                'active_settings_group' => 'kitchen',
            ])
            ->assertRedirect()
            ->assertSessionHas('active_settings_group', 'kitchen');

        $this->assertSame('5', SystemSetting::query()->where('key', 'kds_poll_seconds')->value('value'));
    }

    #[Test]
    public function sales_channel_view_permission_no_longer_requires_settings_manage(): void
    {
        $this->enableModule('sales_channels');
        $viewer = $this->makeUser(
            $this->branch,
            ['sales_channels.view']
        );

        $this->actingAs($viewer)
            ->get(
                route(
                    'settings.sales-channels.index'
                )
            )
            ->assertOk();

        $html = $this->actingAs($viewer)
            ->get(route('profile.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route(
                'settings.sales-channels.index'
            ),
            $html
        );

        $this->assertStringNotContainsString(
            route('settings.index'),
            $html
        );
    }

    #[Test]
    public function payment_method_viewer_can_list_but_cannot_open_edit_screen(): void
    {
        $viewer = $this->makeUser(
            $this->branch,
            ['payment_methods.view']
        );

        $method = $this->makePaymentMethod();

        $this->actingAs($viewer)
            ->get(route('payment-methods.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(
                route(
                    'payment-methods.edit',
                    $method
                )
            )
            ->assertForbidden();
    }

    #[Test]
    public function payment_method_manager_can_open_edit_screen(): void
    {
        $manager = $this->makeUser(
            $this->branch,
            ['payment_methods.manage']
        );

        $method = $this->makePaymentMethod();

        $this->actingAs($manager)
            ->get(
                route(
                    'payment-methods.edit',
                    $method
                )
            )
            ->assertOk();
    }

    #[Test]
    public function staff_pwa_manifest_uses_authorized_start_route_and_installable_metadata(): void
    {
        $response = $this->get(
            route('pwa.manifest')
        )
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/manifest+json; charset=UTF-8'
            )
            ->assertJsonPath(
                'id',
                '/staff-app'
            )
            ->assertJsonPath(
                'display',
                'standalone'
            )
            ->assertJsonPath(
                'dir',
                'rtl'
            );

        $manifest = $response->json();

        $this->assertStringStartsWith(
            '/pwa/start',
            $manifest['start_url']
        );

        $this->assertSame(
            ['192x192', '512x512'],
            collect(
                $manifest['icons']
            )
                ->pluck('sizes')
                ->values()
                ->all()
        );
    }

    #[Test]
    public function installed_pwa_opens_dashboard_when_allowed_and_profile_otherwise(): void
    {
        $dashboardUser = $this->makeUser(
            $this->branch,
            ['dashboard.view']
        );

        $profileOnlyUser = $this->makeUser(
            $this->branch
        );

        $this->actingAs($dashboardUser)
            ->get(route('pwa.start'))
            ->assertRedirect(
                route('dashboard')
            );

        $this->actingAs($profileOnlyUser)
            ->get(route('pwa.start'))
            ->assertRedirect(
                route('profile.show')
            );
    }

    #[Test]
    public function main_layout_contains_install_prompt_service_worker_and_global_sound_policy(): void
    {
        $this->enableModule('reports');
        $user = $this->makeUser(
            $this->branch,
            ['reports.view']
        );

        $html = $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'id="installAppButton"',
            $html
        );

        $this->assertStringContainsString(
            'beforeinstallprompt',
            $html
        );

        $this->assertStringContainsString(
            'serviceWorker.register',
            $html
        );

        $this->assertStringContainsString(
            'notificationSoundGloballyEnabled',
            $html
        );

        $this->assertStringContainsString(
            'التحليلات والتقارير',
            $html
        );

        $serviceWorker = file_get_contents(
            public_path('sw.js')
        );

        $this->assertStringContainsString(
            "request.mode === 'navigate'",
            $serviceWorker
        );

        $this->assertStringContainsString(
            "url.pathname.startsWith('/api/')",
            $serviceWorker
        );

        $this->assertStringContainsString(
            "url.pathname.startsWith('/login')",
            $serviceWorker
        );
    }

    #[Test]
    public function global_notification_dispatcher_targets_relevant_users_and_never_drops_admin_actor(): void
    {
        Notification::fake();

        $actor = $this->makeUser(
            $this->branch,
            ['orders.view']
        );

        $sameBranchRecipient =
            $this->makeUser(
                $this->branch,
                ['orders.view']
            );

        $wrongBranchRecipient =
            $this->makeUser(
                $this->otherBranch,
                ['orders.view']
            );

        $inactiveRecipient =
            $this->makeUser(
                $this->branch,
                ['orders.view'],
                false
            );

        $adminActor = $this->makeUser(
            $this->otherBranch,
            [],
            true,
            'Admin'
        );

        NotificationDispatcher::notifyByPermissions(
            new SystemAuditTestNotification(
                'normal-actor'
            ),
            ['orders.view'],
            $this->branch->id,
            [],
            $actor->id
        );

        Notification::assertNotSentTo(
            $actor,
            SystemAuditTestNotification::class
        );

        Notification::assertSentTo(
            $sameBranchRecipient,
            SystemAuditTestNotification::class
        );

        Notification::assertNotSentTo(
            $wrongBranchRecipient,
            SystemAuditTestNotification::class
        );

        Notification::assertNotSentTo(
            $inactiveRecipient,
            SystemAuditTestNotification::class
        );

        /*
         * Admins are global recipients even outside the affected location.
         */
        Notification::assertSentTo(
            $adminActor,
            SystemAuditTestNotification::class
        );

        Notification::fake();

        NotificationDispatcher::notifyByPermissions(
            new SystemAuditTestNotification(
                'admin-actor'
            ),
            ['orders.view'],
            $this->branch->id,
            [],
            $adminActor->id
        );

        /*
         * Unlike a normal employee, the Admin keeps their own event in the
         * global operational/audit feed.
         */
        Notification::assertSentTo(
            $adminActor,
            SystemAuditTestNotification::class
        );
    }

    private function enableModule(string $code): void
    {
        Module::query()->create([
            'code' => $code,
            'name' => $code,
            'type' => 'core',
            'is_active' => true,
        ]);

        app(ModuleService::class)->invalidate();
    }

    private function makeLocation(
        string $name
    ): Location {
        return Location::query()->create([
            'name' => $name,
            'code' =>
                'SYS-'
                . Str::upper(
                    Str::random(8)
                ),
            'type' => 'branch',
            'is_active' => true,
        ]);
    }

    private function makeUser(
        Location $location,
        array $permissions = [],
        bool $active = true,
        ?string $role = null
    ): User {
        $employee = Employee::query()->create([
            'employee_number' =>
                'SYS-EMP-'
                . Str::upper(
                    Str::random(8)
                ),
            'full_name' =>
                'System Test '
                . Str::random(4),
            'employment_status' => 'active',
        ]);

        $employee
            ->locations()
            ->attach(
                $location->id,
                ['is_primary' => true]
            );

        $user = User::factory()->create([
            'employee_id' => $employee->id,
            'is_active' => $active,
            'must_change_password' => false,
        ]);

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

    private function makePaymentMethod(): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => 'System Test Method',
            'name_ar' => 'طريقة اختبار',
            'code' =>
                'SYS_'
                . Str::upper(
                    Str::random(6)
                ),
            'type' => 'cash',
            'sort_order' => 50,
            'is_active' => true,
            'requires_verification' => false,
            'requires_reference' => false,
        ]);
    }
}

class SystemAuditTestNotification extends LaravelNotification
{
    public function __construct(
        public string $marker
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'system_audit_test',
            'title' => 'System audit',
            'message' => $this->marker,
        ];
    }
}
