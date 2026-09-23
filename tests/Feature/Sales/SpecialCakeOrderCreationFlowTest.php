<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationPaymentMethod;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SpecialCakeOrder;
use App\Models\User;
use App\Notifications\SpecialCakeOrderTransitionedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpecialCakeOrderCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_can_create_cash_cake_order_and_factory_receives_review_notification(): void
    {
        Notification::fake();

        $branch = Location::query()->create([
            'name' => 'فرع إنشاء الكيك',
            'code' => 'CKBR-' . Str::upper(Str::random(5)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $factory = Location::query()->create([
            'name' => 'مصنع إنشاء الكيك',
            'code' => 'CKFA-' . Str::upper(Str::random(5)),
            'type' => 'factory',
            'is_active' => true,
        ]);

        $creator = $this->userAt($branch, [
            'cake_orders.create',
            'cake_orders.view',
        ]);

        $factoryReviewer = $this->userAt($factory, [
            'cake_orders.view',
            'cake_orders.review',
        ]);

        $customer = Customer::query()->create([
            'location_id' => $branch->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'scope' => Customer::SCOPE_BRANCH,
            'name' => 'عميل إنشاء طلب كيك',
            'phone' => '0592345678',
            'allow_credit' => false,
            'billing_cycle' => 'immediate',
            'payment_terms_days' => 0,
        ]);

        $cash = PaymentMethod::query()->create([
            'name' => 'Cash',
            'name_ar' => 'نقدي',
            'code' => 'cash',
            'type' => 'cash',
            'requires_verification' => false,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LocationPaymentMethod::query()->create([
            'location_id' => $branch->id,
            'payment_method_id' => $cash->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($creator)
            ->post(route('cake-orders.store'), [
                'customer_id' => $customer->id,
                'required_date' => now()->addDays(3)->toDateString(),
                'required_time' => '15:30',
                'cake_type' => 'Birthday',
                'cake_size' => 'Large',
                'total_price' => 120,
                'payment_arrangement' => 'pay_now',
                'payment_method_id' => $cash->id,
                'discount_type' => 'none',
                'discount_value' => 0,
                'image_cover_type' => 'none',
            ]);

        $order = SpecialCakeOrder::query()->sole();

        $response
            ->assertRedirect(route('cake-orders.show', $order))
            ->assertSessionHas(
                'success',
                'تم إنشاء طلب الكيك وإرساله إلى المصنع للمراجعة.'
            );

        $order->refresh();

        $this->assertSame(
            'pending_factory_review',
            $order->status->value
        );

        $this->assertSame(
            'pay_on_pickup',
            $order->payment_arrangement->value
        );

        $this->assertSame(
            $branch->id,
            $order->origin_branch_id
        );

        $this->assertSame(
            $factory->id,
            $order->factory_location_id
        );

        $this->assertDatabaseHas('cake_order_status_histories', [
            'special_cake_order_id' => $order->id,
            'from_status' => 'draft',
            'to_status' => 'pending_factory_review',
            'changed_by' => $creator->id,
        ]);

        $this->assertSame(
            0,
            Payment::query()
                ->where('order_type', 'special_cake_order')
                ->where('order_id', $order->id)
                ->count()
        );

        Notification::assertSentTo(
            $factoryReviewer,
            SpecialCakeOrderTransitionedNotification::class,
            fn ($notification) =>
                $notification->toDatabase($factoryReviewer)['to_status']
                === 'pending_factory_review'
        );

        Notification::assertNotSentTo(
            $creator,
            SpecialCakeOrderTransitionedNotification::class
        );
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

        foreach ($permissions as $name) {
            $user->givePermissionTo(
                Permission::findOrCreate($name, 'web')
            );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user->fresh();
    }
}
