<?php

namespace Tests\Feature\Notifications;

use App\Events\SpecialCakeOrderTransitioned;
use App\Listeners\NotifyStaffOnSpecialCakeOrderTransitioned;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\SpecialCakeOrderTransitionedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpecialCakeNotificationDedupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function duplicate_cake_transition_event_is_not_stored_twice_even_after_cache_flush(): void
    {
        $branch = $this->location('branch', 'DEDUP-BR');
        $factory = $this->location('factory', 'DEDUP-FA');

        $actor = User::factory()->create();

        $adminRole = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $actor->assignRole($adminRole);

        $recipient = User::factory()->create();

        $recipient->employee->locations()->attach($factory->id, [
            'is_primary' => true,
        ]);

        foreach (['cake_orders.view', 'cake_orders.review'] as $name) {
            $recipient->givePermissionTo(
                Permission::findOrCreate($name, 'web')
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'عميل منع التكرار',
            'phone' => '0597654321',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        $order = SpecialCakeOrder::query()->create([
            'order_number' => 'CKO-DEDUP-' . Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'origin_branch_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'required_date' => now()->addDays(2)->toDateString(),
            'total_price' => 100,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_price' => 100,
            'status' => 'pending_factory_review',
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'created_by' => $actor->id,
        ]);

        $event = new SpecialCakeOrderTransitioned(
            $order,
            'draft',
            'pending_factory_review',
            $actor,
            'اختبار منع الإشعار المكرر'
        );

        $listener = app(
            NotifyStaffOnSpecialCakeOrderTransitioned::class
        );

        Cache::flush();
        $listener->handle($event);

        // Simulate cache loss/restart. DB fingerprint fallback must still
        // prevent the same transition notification from being inserted again.
        Cache::flush();
        $listener->handle($event);

        $this->assertSame(
            1,
            $recipient->notifications()
                ->where(
                    'type',
                    SpecialCakeOrderTransitionedNotification::class
                )
                ->where(
                    'data->fingerprint',
                    "cake_transition_{$order->id}_draft_pending_factory_review"
                )
                ->count()
        );
    }

    private function location(string $type, string $prefix): Location
    {
        return Location::query()->create([
            'name' => $prefix,
            'code' => $prefix . '-' . Str::upper(Str::random(4)),
            'type' => $type,
            'is_active' => true,
        ]);
    }
}
