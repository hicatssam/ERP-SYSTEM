<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Models\Location;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\SpecialCakeOrderTransitionedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialCakeNotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cake_notification_is_exposed_in_header_api_and_notification_center(): void
    {
        $branch = Location::query()->create([
            'name' => 'فرع الإشعارات',
            'code' => 'NOT-BR-' . Str::upper(Str::random(4)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'مصنع الإشعارات',
            'code' => 'NOT-FA-' . Str::upper(Str::random(4)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $user->employee->locations()->attach($branch->id, [
            'is_primary' => true,
        ]);

        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'عميل إشعارات الكيك',
            'phone' => '0591234567',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        $order = SpecialCakeOrder::query()->create([
            'order_number' => 'CKO-NOT-' . Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'origin_branch_id' => $branch->id,
            'factory_location_id' => $factory->id,
            'required_date' => now()->addDays(2)->toDateString(),
            'total_price' => 80,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_price' => 80,
            'status' => 'pending_factory_review',
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'created_by' => $user->id,
        ]);

        $user->notify(
            new SpecialCakeOrderTransitionedNotification(
                $order,
                'draft',
                'pending_factory_review',
                'ملاحظة اختبار'
            )
        );

        $this->actingAs($user)
            ->getJson(route('notifications.recent'))
            ->assertOk()
            ->assertJsonPath(
                'items.0.type',
                'cake_order_transitioned'
            )
            ->assertJsonPath(
                'items.0.url',
                '/cake-orders/' . $order->id
            )
            ->assertJsonPath(
                'items.0.title',
                'تحديث طلب كيك #' . $order->order_number
            );

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('بانتظار مراجعة المصنع')
            ->assertSee('فرع الإشعارات')
            ->assertSee('مصنع الإشعارات');
    }
}
