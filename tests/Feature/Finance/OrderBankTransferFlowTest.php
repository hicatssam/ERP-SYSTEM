<?php

namespace Tests\Feature\Finance;

use App\Models\Employee;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderBankTransferFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_bank_transfer_is_forced_pending_reserves_balance_and_can_be_verified(): void
    {
        [$user, $location] = $this->makeFinanceUser();
        [$method, $account] = $this->makeBankMethodAndAccount($location);

        $order = Order::query()->create([
            'order_number' => 'ORD-BANK-' . Str::upper(Str::random(6)),
            'location_id' => $location->id,
            'created_by' => $user->id,
            'status' => 'confirmed',
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'subtotal' => 100,
            'total_amount' => 100,
            'confirmed_at' => now(),
        ]);

        $this->mock(
            FinancialPostingService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('collection')
                    ->once()
                    ->andReturnNull();
            }
        );

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('payments.store'), [
                'entry_context' => 'order_bank_transfer',
                'order_type' => 'order',
                'order_id' => $order->id,
                'payment_method_id' => $method->id,
                'location_payment_account_id' => $account->id,
                'amount' => 100,
                'sender_name' => 'محمود أحمد',
                'sender_phone' => '0599000000',
                'sender_account_number' => 'SENDER-778899',
                'reference_number' => 'TX-10001',
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success', 'تم تسجيل الحوالة وهي الآن بانتظار التحقق.');

        $payment = Payment::query()->sole();

        $this->assertSame('pending_verification', $payment->statusValue());
        $this->assertSame($account->id, $payment->location_payment_account_id);
        $this->assertSame('محمود أحمد', $payment->sender_name);
        $this->assertSame('0599000000', $payment->sender_phone);
        $this->assertSame('SENDER-778899', $payment->sender_account_number);
        $this->assertSame('payment_pending', $order->fresh()->payment_status->value);

        // The pending transfer already reserves the full order balance.
        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('payments.store'), [
                'entry_context' => 'order_bank_transfer',
                'order_type' => 'order',
                'order_id' => $order->id,
                'payment_method_id' => $method->id,
                'location_payment_account_id' => $account->id,
                'amount' => 1,
                'sender_name' => 'محاولة ثانية',
                'sender_account_number' => 'SENDER-2',
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, Payment::query()->count());

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('payments.verify', $payment), [
                'action' => 'verify',
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success', 'تم التحقق من الدفعة وتأكيدها.');

        $payment->refresh();
        $order->refresh();

        $this->assertSame('confirmed', $payment->statusValue());
        $this->assertSame($user->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame('paid', $order->payment_status->value);

        $this->actingAs($user)
            ->get(route('payments.bank-sales', [
                'search' => 'SENDER-778899',
            ]))
            ->assertOk()
            ->assertSee('محمود أحمد')
            ->assertSee('SENDER-778899')
            ->assertSee('TX-10001');
    }

    #[Test]
    public function order_bank_transfer_context_rejects_non_banking_payment_methods(): void
    {
        [$user, $location] = $this->makeFinanceUser();

        $cashMethod = PaymentMethod::query()->create([
            'name' => 'Cash',
            'name_ar' => 'نقدي',
            'code' => 'cash-test-' . Str::lower(Str::random(6)),
            'type' => 'cash',
            'requires_verification' => false,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $order = Order::query()->create([
            'order_number' => 'ORD-CASH-' . Str::upper(Str::random(6)),
            'location_id' => $location->id,
            'created_by' => $user->id,
            'status' => 'confirmed',
            'payment_status' => 'payment_pending',
            'payment_arrangement' => 'pay_on_pickup',
            'subtotal' => 50,
            'total_amount' => 50,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('payments.store'), [
                'entry_context' => 'order_bank_transfer',
                'order_type' => 'order',
                'order_id' => $order->id,
                'payment_method_id' => $cashMethod->id,
                'amount' => 50,
                'sender_name' => 'اختبار نقدي',
                'sender_account_number' => 'INVALID',
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('payment_method_id');

        $this->assertDatabaseCount('payments', 0);
    }

    private function makeFinanceUser(): array
    {
        $location = Location::query()->create([
            'name' => 'Bank Transfer Test Branch ' . Str::random(5),
            'code' => 'BT-' . Str::upper(Str::random(6)),
            'type' => 'branch',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'employee_number' => 'EMP-' . Str::upper(Str::random(6)),
            'full_name' => 'Finance Tester',
            'employment_status' => 'active',
        ]);

        $employee->locations()->attach($location->id, [
            'is_primary' => true,
        ]);

        $user = User::factory()->create([
            'employee_id' => $employee->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        foreach (['payments.record', 'payments.verify'] as $permissionName) {
            $permission = Permission::findOrCreate($permissionName, 'web');
            $user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$user, $location];
    }

    private function makeBankMethodAndAccount(Location $location): array
    {
        // Verification is intentionally disabled on the method itself. The
        // order-bank-transfer context must still force the payment to pending.
        $method = PaymentMethod::query()->create([
            'name' => 'Manual Bank Transfer',
            'name_ar' => 'تحويل بنكي يدوي',
            'code' => 'bank-test-' . Str::lower(Str::random(6)),
            'type' => 'bank_transfer',
            'requires_verification' => false,
            'requires_reference' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $account = LocationPaymentAccount::query()->create([
            'location_id' => $location->id,
            'payment_method_id' => $method->id,
            'name' => 'حساب الاستلام الرئيسي',
            'provider_name' => 'Test Bank',
            'account_holder_name' => 'Dahab Sweets',
            'account_number' => 'ACC-123456',
            'iban' => 'PS00TEST000000000000000000000',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$method, $account];
    }
}
