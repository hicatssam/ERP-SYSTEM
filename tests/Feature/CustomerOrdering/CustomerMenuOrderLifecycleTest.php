<?php

namespace Tests\Feature\CustomerOrdering;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerMenuOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_order_fields_are_persisted_and_status_endpoint_reflects_confirmation(): void
    {
        $location = $this->makeLocation();
        $actor = User::factory()->create();
        $publicToken = (string) Str::uuid();
        $requestToken = (string) Str::uuid();

        $order = Order::query()->create([
            'order_number' => 'WEB-' . Str::upper(Str::random(8)),
            'location_id' => $location->id,
            'created_by' => $actor->id,
            'status' => 'draft',
            'payment_status' => 'pending_payment_verification',
            'payment_arrangement' => 'pending_verification',
            'subtotal' => 25,
            'total_amount' => 25,
            'order_source' => 'customer_menu',
            'public_token' => $publicToken,
            'public_request_id' => $requestToken,
            'public_request_token' => $requestToken,
            'public_order_meta' => ['source' => 'customer_menu'],
            'customer_status_message' => 'سيتم قبول الطلب بعد مراجعة الدفع.',
            'customer_status_message_updated_at' => now(),
            'customer_status_message_by' => $actor->id,
        ]);

        $this->assertSame($requestToken, $order->fresh()->public_request_id);
        $this->assertSame(['source' => 'customer_menu'], $order->fresh()->public_order_meta);

        $this->getJson(route('customer-menu.status', ['token' => $publicToken]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('state', 'received')
            ->assertJsonPath('payment_status', 'pending_payment_verification')
            ->assertJsonPath('payment_arrangement', 'pending_verification')
            ->assertJsonPath('customer_message', 'سيتم قبول الطلب بعد مراجعة الدفع.');

        $order->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->getJson(route('customer-menu.status', ['token' => $publicToken]))
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('state', 'accepted')
            ->assertJsonPath('step', 2);
    }

    #[Test]
    public function customer_can_open_only_the_invoice_linked_to_the_public_order_token(): void
    {
        $location = $this->makeLocation();
        $actor = User::factory()->create();
        $publicToken = (string) Str::uuid();

        $order = Order::query()->create([
            'order_number' => 'WEB-' . Str::upper(Str::random(8)),
            'location_id' => $location->id,
            'created_by' => $actor->id,
            'status' => 'confirmed',
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'subtotal' => 42,
            'total_amount' => 42,
            'order_source' => 'customer_menu',
            'public_token' => $publicToken,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-' . Str::upper(Str::random(8)),
            'invoice_type' => 'regular_order',
            'order_type' => 'order',
            'order_id' => $order->id,
            'location_id' => $location->id,
            'status' => 'active',
            'subtotal' => 42,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 42,
            'paid_amount' => 0,
            'remaining_amount' => 42,
            'issued_by' => $actor->id,
            'issued_at' => now(),
        ]);

        $this->get(route('customer-menu.invoice', ['token' => $publicToken]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($order->order_number);

        $this->get(route('customer-menu.invoice', ['token' => (string) Str::uuid()]))
            ->assertNotFound();
    }

    #[Test]
    public function complete_permission_can_finish_a_confirmed_order_without_update_permission(): void
    {
        $location = $this->makeLocation();
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-' . Str::upper(Str::random(6)),
            'full_name' => 'Order Finisher',
            'employment_status' => 'active',
        ]);
        $employee->locations()->attach($location->id, ['is_primary' => true]);

        $user = User::factory()->create(['employee_id' => $employee->id]);
        $permission = Permission::findOrCreate('orders.complete', 'web');
        $role = Role::create([
            'name' => 'OrderFinisher-' . Str::random(8),
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $order = Order::query()->create([
            'order_number' => 'WEB-' . Str::upper(Str::random(8)),
            'location_id' => $location->id,
            'created_by' => $user->id,
            'status' => 'confirmed',
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'subtotal' => 25,
            'total_amount' => 25,
        ]);

        $this->assertTrue($user->can('complete', $order));
        $this->assertFalse($user->can('update', $order));
    }

    private function makeLocation(): Location
    {
        return Location::query()->create([
            'name' => 'Customer Menu Branch ' . Str::random(6),
            'code' => 'CMB-' . Str::upper(Str::random(6)),
            'type' => 'branch',
            'is_active' => true,
        ]);
    }
}
