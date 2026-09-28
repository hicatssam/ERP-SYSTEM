<?php

namespace Tests\Feature\Finance;

use App\Models\BranchCashMovement;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyCashReconciliation;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DailyCashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_cash_totals_use_only_real_cash_and_next_opening_uses_actual_close(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
        $branch = $this->branch();
        $user = $this->userAt($branch, ['financial.cash.view', 'financial.cash.movements.manage', 'financial.cash.close']);
        $cash = $this->method('cash');
        $bank = $this->method('bank_transfer');

        $paymentId = $this->payment($branch, $user, $cash, 100, 'confirmed');
        $this->payment($branch, $user, $bank, 900, 'confirmed');
        $this->payment($branch, $user, $cash, 60, 'pending_verification');
        $customer = Customer::query()->create(['name' => 'Test Customer', 'phone' => '059'.Str::random(7)]);
        CustomerPayment::query()->create([
            'customer_id' => $customer->id, 'location_id' => $branch->id,
            'payment_method_id' => $cash->id, 'amount' => 40, 'status' => 'confirmed',
            'received_by' => $user->id, 'paid_at' => now(),
        ]);
        DB::table('refunds')->insert([
            'payment_id' => $paymentId, 'order_type' => 'order', 'order_id' => 999,
            'payment_method_id' => $cash->id, 'amount' => 10,
            'reason' => 'test', 'processed_by' => $user->id, 'processed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $category = ExpenseCategory::query()->create(['code' => 'TEST', 'name' => 'Other']);
        $this->expense($branch, $user, $category, $cash, 15);
        $this->expense($branch, $user, $category, $bank, 200);
        $this->supplierPayment($branch, $user, $cash, 20);

        foreach (['other_income' => 8, 'transfer_in' => 12, 'transfer_out' => 5] as $type => $amount) {
            $this->actingAs($user)->post(route('daily-cash.movements.store'), [
                'location_id' => $branch->id, 'date' => '2026-09-28',
                'type' => $type, 'amount' => $amount, 'description' => 'Test movement',
                'reference' => 'REF-'.Str::upper($type),
            ])->assertRedirect();
        }

        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 50, 'actual_closing' => 157, 'note' => 'Counted cash shortage',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $closed = DailyCashReconciliation::query()->sole();
        $this->assertEquals(100, $closed->sales);
        $this->assertEquals(40, $closed->customer_receipts);
        $this->assertEquals(15, $closed->expenses);
        $this->assertEquals(20, $closed->supplier_payments);
        $this->assertEquals(10, $closed->refunds);
        $this->assertEquals(160, $closed->expected_closing);
        $this->assertEquals(157, $closed->actual_closing);
        $this->assertEquals(-3, $closed->variance);

        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 999, 'actual_closing' => 999,
        ])->assertSessionHasErrors('date');

        $this->travelTo(now()->addDay());
        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-29',
            'actual_closing' => 160, 'note' => 'Found extra cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $next = DailyCashReconciliation::query()->where('business_date', '2026-09-29')->firstOrFail();
        $this->assertEquals(157, $next->opening_balance);
        $this->assertEquals(157, $next->expected_closing);
        $this->assertEquals(3, $next->variance);
    }

    public function test_branch_scope_and_separate_permissions_protect_read_and_write(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
        $first = $this->branch();
        $second = $this->branch();
        $reader = $this->userAt($first, ['financial.cash.view']);

        $this->actingAs($reader)->get(route('daily-cash.index', ['location_id' => $first->id]))->assertOk();
        $this->actingAs($reader)->get(route('daily-cash.index', ['location_id' => $second->id]))->assertForbidden();
        $this->actingAs($reader)->post(route('daily-cash.movements.store'), [
            'location_id' => $first->id, 'date' => '2026-09-28', 'type' => 'other_income',
            'amount' => 10, 'description' => 'Cash income',
        ])->assertForbidden();
        $this->actingAs($reader)->post(route('daily-cash.close'), [
            'location_id' => $first->id, 'date' => '2026-09-28',
            'opening_balance' => 10, 'actual_closing' => 10,
        ])->assertForbidden();
        $manager = $this->userAt($first, ['financial.cash.view', 'financial.cash.movements.manage', 'financial.cash.close']);
        $this->actingAs($manager)->post(route('daily-cash.movements.store'), [
            'location_id' => $second->id, 'date' => '2026-09-28', 'type' => 'other_income',
            'amount' => 10, 'description' => 'Wrong branch',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('daily-cash.close'), [
            'location_id' => $second->id, 'date' => '2026-09-28',
            'opening_balance' => 10, 'actual_closing' => 10,
        ])->assertForbidden();
        $this->assertSame(0, DailyCashReconciliation::query()->count());
    }

    public function test_unclassified_expense_blocks_close_until_authorized_classification(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
        $branch = $this->branch();
        $user = $this->userAt($branch, ['financial.cash.view', 'financial.cash.close', 'expenses.view', 'expenses.post']);
        $cash = $this->method('cash');
        $category = ExpenseCategory::query()->create(['code' => 'TEST', 'name' => 'Other']);
        $expense = $this->expense($branch, $user, $category, null, 12);

        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 20, 'actual_closing' => 8,
        ])->assertSessionHasErrors('cash');
        $this->actingAs($user)->post(route('daily-cash.expenses.classify', $expense), [
            'payment_method_id' => $cash->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($cash->id, $expense->fresh()->payment_method_id);

        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 20, 'actual_closing' => 8,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(8, DailyCashReconciliation::query()->sole()->expected_closing);
    }

    public function test_closed_day_rejects_backdated_movements_and_reports_source_drift(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
        $branch = $this->branch();
        $user = $this->userAt($branch, ['financial.cash.view', 'financial.cash.movements.manage', 'financial.cash.close']);
        $cash = $this->method('cash');

        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 100, 'actual_closing' => 100,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('daily-cash.movements.store'), [
            'location_id' => $branch->id, 'date' => '2026-09-28', 'type' => 'transfer_out',
            'amount' => 20, 'description' => 'Cash deposit',
        ])->assertSessionHasErrors('date');

        $this->payment($branch, $user, $cash, 10, 'confirmed');
        $this->actingAs($user)->get(route('daily-cash.index', [
            'location_id' => $branch->id, 'date' => '2026-09-28',
        ]))->assertOk()->assertSee('تغيّرت حركة مصدر بعد إقفال اليوم');
        $this->assertEquals(100, DailyCashReconciliation::query()->sole()->expected_closing);
    }

    public function test_foreign_cash_supplier_payment_is_not_silently_converted_into_physical_base_cash(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
        $branch = $this->branch();
        $user = $this->userAt($branch, ['financial.cash.view', 'financial.cash.close']);
        $cash = $this->method('cash');
        $this->supplierPayment($branch, $user, $cash, 20, false);

        $this->actingAs($user)->get(route('daily-cash.index', ['location_id' => $branch->id]))
            ->assertOk()->assertSee('دفعة مورد نقدية بعملة غير أساسية');
        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 50, 'actual_closing' => 50,
        ])->assertSessionHasErrors('cash');
    }

    public function test_voided_manual_movement_is_excluded_and_retained_for_audit(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
        $branch = $this->branch();
        $user = $this->userAt($branch, ['financial.cash.view', 'financial.cash.movements.manage', 'financial.cash.close']);

        $this->actingAs($user)->post(route('daily-cash.movements.store'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'type' => 'other_income', 'amount' => 10, 'description' => 'Test income',
        ])->assertSessionHasNoErrors();
        $movement = BranchCashMovement::query()->sole();
        $this->actingAs($user)->post(route('daily-cash.movements.void', $movement), [
            'reason' => 'Entry made in error',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($movement->fresh()->voided_at);

        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 10, 'actual_closing' => 10,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(0, DailyCashReconciliation::query()->sole()->other_income);
        $this->actingAs($user)->post(route('daily-cash.movements.void', $movement), [
            'reason' => 'Attempt after closing',
        ])->assertSessionHasErrors('movement');
    }

    public function test_posted_cash_payroll_is_an_expense_but_bank_payroll_is_not(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0));
        $branch = $this->branch();
        $user = $this->userAt($branch, ['financial.cash.view', 'financial.cash.close']);
        $cash = $this->method('cash');
        $bank = $this->method('bank_transfer');
        $currency = Currency::query()->create([
            'code' => 'ILS', 'name' => 'Shekel', 'is_base' => true, 'is_active' => true,
        ]);
        $period = DB::table('payroll_periods')->insertGetId([
            'code' => 'PAY-2026-09', 'name' => 'September',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = DB::table('payroll_items')->insertGetId([
            'payroll_period_id' => $period, 'employee_id' => $user->employee_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$cash, 7], [$bank, 30]] as [$method, $amount]) {
            DB::table('payroll_payments')->insert([
                'payroll_item_id' => $item, 'employee_id' => $user->employee_id,
                'location_id' => $branch->id, 'currency_id' => $currency->id,
                'amount' => $amount, 'base_amount' => $amount,
                'payment_method_id' => $method->id, 'paid_at' => now(),
                'status' => 'posted', 'created_by' => $user->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)->post(route('daily-cash.close'), [
            'location_id' => $branch->id, 'date' => '2026-09-28',
            'opening_balance' => 20, 'actual_closing' => 13,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(7, DailyCashReconciliation::query()->sole()->expenses);
        $this->assertEquals(13, DailyCashReconciliation::query()->sole()->expected_closing);
    }

    private function branch(): Location
    {
        return Location::query()->create([
            'name' => 'Branch '.Str::random(5), 'code' => 'B-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function userAt(Location $branch, array $permissions): User
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-'.Str::upper(Str::random(6)),
            'full_name' => 'Cash Tester', 'employment_status' => 'active',
        ]);
        $employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user = User::factory()->create([
            'employee_id' => $employee->id, 'is_active' => true, 'must_change_password' => false,
        ]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function method(string $type): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => $type, 'name_ar' => $type, 'code' => Str::upper($type).Str::upper(Str::random(5)),
            'type' => $type, 'is_active' => true,
        ]);
    }

    private function payment(Location $branch, User $user, PaymentMethod $method, float $amount, string $status): int
    {
        return DB::table('payments')->insertGetId([
            'order_type' => 'order', 'order_id' => 999,
            'payment_method_id' => $method->id, 'location_id' => $branch->id,
            'amount' => $amount, 'status' => $status, 'received_by' => $user->id,
            'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function expense(Location $branch, User $user, ExpenseCategory $category, ?PaymentMethod $method, float $amount): Expense
    {
        return Expense::query()->create([
            'expense_category_id' => $category->id, 'payment_method_id' => $method?->id,
            'location_id' => $branch->id, 'amount' => $amount, 'expense_date' => now()->toDateString(),
            'description' => 'Test expense', 'status' => 'posted', 'created_by' => $user->id,
        ]);
    }

    private function supplierPayment(Location $branch, User $user, PaymentMethod $method, float $amount, bool $base = true): void
    {
        $currency = Currency::query()->create([
            'code' => $base ? 'ILS' : 'USD', 'name' => $base ? 'Shekel' : 'Dollar',
            'symbol' => $base ? '₪' : '$', 'is_base' => $base, 'is_active' => true,
        ]);
        $supplier = DB::table('suppliers')->insertGetId([
            'supplier_code' => 'SUP-'.Str::upper(Str::random(5)), 'name' => 'Supplier',
            'currency_id' => $currency->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoice = DB::table('supplier_invoices')->insertGetId([
            'invoice_number' => 'INV-'.Str::upper(Str::random(5)),
            'supplier_id' => $supplier, 'location_id' => $branch->id,
            'currency_id' => $currency->id, 'invoice_date' => now()->toDateString(),
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('supplier_payments')->insert([
            'payment_number' => 'SPY-'.Str::upper(Str::random(5)),
            'supplier_id' => $supplier, 'supplier_invoice_id' => $invoice,
            'location_id' => $branch->id, 'currency_id' => $currency->id,
            'amount' => $amount, 'base_amount' => $amount, 'applied_amount' => $amount,
            'payment_date' => now()->toDateString(), 'payment_method_id' => $method->id,
            'status' => 'confirmed', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
