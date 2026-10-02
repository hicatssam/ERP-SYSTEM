<?php

namespace Tests\Feature\Finance;

use App\Models\Currency;
use App\Models\DailyCashReconciliation;
use App\Models\Employee;
use App\Models\EmployeePurchase;
use App\Models\EmployeePurchaseReceipt;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Location;
use App\Models\LocationProduct;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use App\Services\Finance\DailyCashReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeePurchaseAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_snapshots_prices_consumes_batch_stock_once_and_splits_installments_without_affecting_salary(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $employee, $user] = $this->setupBranch(['accounting.employee_accounts.create', 'accounting.employee_accounts.view']);
        $this->baseCurrency();
        $first = $this->product($branch, 10, 8, 5);
        $second = $this->product($branch, 0.01, 4, 4, true);
        $batch = InventoryBatch::query()->create([
            'location_id' => $branch->id, 'product_id' => $second->id,
            'batch_number' => 'BATCH-TEST', 'received_quantity' => 4,
            'available_quantity' => 4, 'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        $request = $this->payload($branch, $employee, $first, 2);
        $request['items'][] = ['product_id' => $second->id, 'quantity' => 1];
        $request['payment_plan'] = 'installments';
        $request['installment_count'] = 3;
        $this->actingAs($user)->post(route('accounting.employee-purchases.store'), $request)
            ->assertRedirect()->assertSessionHasNoErrors();

        $purchase = EmployeePurchase::query()->with('installments')->sole();
        $this->assertEquals(10, $purchase->items()->where('product_id', $first->id)->value('unit_price'));
        $this->assertEquals(20.01, $purchase->total_amount);
        $this->assertEquals([6.67, 6.67, 6.67], $purchase->installments->pluck('amount')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(6, Inventory::query()->where('product_id', $first->id)->value('quantity'));
        $this->assertEquals(3, $batch->fresh()->available_quantity);
        $this->assertSame(2, DB::table('stock_movements')->where('reason', 'employee_purchase')->count());
        $this->assertSame(0, DB::table('employee_ledger_entries')->count());
        $this->assertSame(1, SalesLedgerEntry::query()->where('entry_type', 'employee_purchase')->count());

        // A retried HTTP request must return the same purchase without removing stock twice.
        $this->actingAs($user)->post(route('accounting.employee-purchases.store'), $request)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, EmployeePurchase::query()->count());
        $this->assertEquals(6, Inventory::query()->where('product_id', $first->id)->value('quantity'));

        $this->actingAs($user)->get(route('accounting.employee-purchases.show', $purchase))
            ->assertOk()->assertSee('جدول الاستحقاقات')->assertSee('20.01');
    }

    public function test_cash_receipts_reduce_oldest_installment_and_count_in_cash_once_on_posting_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $employee, $user] = $this->setupBranch([
            'accounting.employee_accounts.create', 'accounting.employee_accounts.view',
            'accounting.employee_accounts.receive', 'financial.cash.view',
        ]);
        $this->baseCurrency();
        $product = $this->product($branch, 10, 5, 5);
        $data = $this->payload($branch, $employee, $product, 1);
        $data['payment_plan'] = 'installments';
        $data['installment_count'] = 2;
        $this->actingAs($user)->post(route('accounting.employee-purchases.store'), $data)->assertSessionHasNoErrors();
        $purchase = EmployeePurchase::query()->sole();
        $cash = $this->method('cash');
        $receiptData = ['request_key' => (string) Str::uuid(), 'amount' => '6.00', 'payment_method_id' => $cash->id];
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.store', $purchase), $receiptData)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(5, $purchase->fresh()->installments()->first()->paid_amount);
        $this->assertEquals(1, $purchase->fresh()->installments()->where('sequence', 2)->first()->paid_amount);
        $this->assertEquals(4, $purchase->fresh()->outstanding_amount);
        $totals = app(DailyCashReconciliationService::class)->forDay($branch->id, now()->toDateString());
        $this->assertEquals(6, $totals['components']['employee_receipts']);
        $this->assertEquals(6, app(DailyCashReconciliationService::class)->expected(0, $totals['components']));
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.store', $purchase), $receiptData)
            ->assertSessionHasNoErrors();
        $this->assertSame(1, EmployeePurchaseReceipt::query()->count());
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.store', $purchase),
            ['request_key' => (string) Str::uuid(), 'amount' => '5.00', 'payment_method_id' => $cash->id])
            ->assertSessionHasErrors('amount');

        DailyCashReconciliation::query()->create([
            'location_id' => $branch->id, 'business_date' => now()->toDateString(),
            'opening_balance' => 0, 'expected_closing' => 6,
            'actual_closing' => 6, 'variance' => 0,
            'closed_by' => $user->id, 'closed_at' => now(),
        ]);
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.store', $purchase),
            ['request_key' => (string) Str::uuid(), 'amount' => '4.00', 'payment_method_id' => $cash->id])
            ->assertSessionHasErrors('receipt');
        $this->assertSame(1, EmployeePurchaseReceipt::query()->count());
        $this->assertSame(1, SalesLedgerEntry::query()->where('entry_type', 'employee_collection')->count());
    }

    public function test_pending_bank_proof_is_private_and_verification_reserves_then_posts_or_rejects(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        Storage::fake('local');
        [$branch, $employee, $user] = $this->setupBranch([
            'accounting.employee_accounts.create', 'accounting.employee_accounts.view',
            'accounting.employee_accounts.receive', 'accounting.employee_accounts.verify',
        ]);
        [, , $outsider] = $this->setupBranch(['accounting.employee_accounts.view', 'accounting.employee_accounts.receive']);
        $this->baseCurrency();
        $product = $this->product($branch, 20, 5, 5);
        $this->actingAs($user)->post(route('accounting.employee-purchases.store'),
            $this->payload($branch, $employee, $product, 1))->assertSessionHasNoErrors();
        $purchase = EmployeePurchase::query()->sole();
        $bank = $this->method('bank_transfer', true);
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.store', $purchase), [
            'request_key' => (string) Str::uuid(), 'amount' => 15,
            'payment_method_id' => $bank->id, 'reference' => 'BANK-1',
            'payment_proof' => UploadedFile::fake()->image('bank.png'),
        ])->assertSessionHasNoErrors();
        $receipt = EmployeePurchaseReceipt::query()->sole();
        $this->assertSame('pending_verification', $receipt->status);
        $this->assertEquals(20, $purchase->fresh()->outstanding_amount);
        $this->assertSame(0, SalesLedgerEntry::query()->where('entry_type', 'employee_collection')->count());
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.store', $purchase), [
            'request_key' => (string) Str::uuid(), 'amount' => 10,
            'payment_method_id' => $bank->id, 'reference' => 'BANK-2',
            'payment_proof' => UploadedFile::fake()->image('bank.png'),
        ])->assertSessionHasErrors('amount');
        $this->actingAs($outsider)->get(route('accounting.employee-purchases.show', $purchase))->assertForbidden();
        $this->actingAs($outsider)->get(route('accounting.employee-purchases.receipts.proof', $receipt))->assertForbidden();
        $this->actingAs($outsider)->post(route('accounting.employee-purchases.receipts.store', $purchase), [
            'request_key' => (string) Str::uuid(), 'amount' => 1, 'payment_method_id' => $bank->id,
        ])->assertForbidden();
        $this->actingAs($user)->get(route('accounting.employee-purchases.receipts.proof', $receipt))->assertOk();
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.verify', $receipt))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(5, $purchase->fresh()->outstanding_amount);
        $this->assertSame('posted', $receipt->fresh()->status);
        $this->assertSame(1, SalesLedgerEntry::query()->where('entry_type', 'employee_collection')->count());
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.verify', $receipt))
            ->assertSessionHasErrors('receipt');

        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.store', $purchase), [
            'request_key' => (string) Str::uuid(), 'amount' => 5,
            'payment_method_id' => $bank->id, 'reference' => 'BANK-3',
            'payment_proof' => UploadedFile::fake()->image('bank.png'),
        ])->assertSessionHasNoErrors();
        $last = EmployeePurchaseReceipt::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('accounting.employee-purchases.receipts.reject', $last), ['reason' => 'إثبات غير صحيح'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', $last->fresh()->status);
        $this->assertEquals(5, $purchase->fresh()->outstanding_amount);
        $this->assertSame(2, EmployeePurchaseReceipt::query()->count());
    }

    public function test_insufficient_stock_rolls_back_all_items_and_permissions_hide_accounts(): void
    {
        [$branch, $employee, $user] = $this->setupBranch(['accounting.employee_accounts.create']);
        [$foreign] = $this->setupBranch(['accounting.employee_accounts.create']);
        $this->baseCurrency();
        $first = $this->product($branch, 10, 5, 5);
        $empty = $this->product($branch, 5, 1, 1);
        $request = $this->payload($branch, $employee, $first, 2);
        $request['items'][] = ['product_id' => $empty->id, 'quantity' => 2];
        $this->actingAs($user)->post(route('accounting.employee-purchases.store'), $request)
            ->assertSessionHasErrors('quantity');
        $this->assertSame(0, EmployeePurchase::query()->count());
        $this->assertEquals(5, Inventory::query()->where('product_id', $first->id)->value('quantity'));
        $this->actingAs($user)->get(route('accounting.employee-purchases.index'))->assertForbidden();
        $this->actingAs($user)->post(route('accounting.employee-purchases.store'),
            $this->payload($foreign, $employee, $first, 1))->assertForbidden();
    }

    public function test_ledger_hides_employee_purchase_entries_without_employee_accounts_permission(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $employee, $creator] = $this->setupBranch([
            'accounting.employee_accounts.create', 'accounting.employee_accounts.view', 'financial.reports.view',
        ]);
        [, , $reader] = $this->setupBranch(['financial.reports.view']);
        $reader->employee->locations()->detach();
        $reader->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $this->baseCurrency();
        $product = $this->product($branch, 8, 5, 5);
        $this->actingAs($creator)->post(route('accounting.employee-purchases.store'),
            $this->payload($branch, $employee, $product, 1))->assertSessionHasNoErrors();
        $purchase = EmployeePurchase::query()->sole();
        $this->actingAs($reader)->get(route('accounting.ledger.index'))
            ->assertOk()->assertDontSee($purchase->number)->assertDontSee('مشتريات موظف');
        $this->actingAs($creator)->get(route('accounting.ledger.index'))
            ->assertOk()->assertSee($purchase->number)->assertSee('مشتريات موظف');
    }

    private function setupBranch(array $permissions): array
    {
        $branch = Location::query()->create([
            'name' => 'Branch '.Str::random(6), 'code' => 'B-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-'.Str::upper(Str::random(6)),
            'full_name' => 'Purchase Tester '.Str::random(5), 'employment_status' => 'active',
        ]);
        $employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user = User::factory()->create([
            'employee_id' => $employee->id, 'is_active' => true, 'must_change_password' => false,
        ]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$branch, $employee, $user];
    }

    private function baseCurrency(): Currency
    {
        return Currency::query()->create([
            'code' => 'ILS', 'name' => 'Shekel', 'symbol' => '₪',
            'is_base' => true, 'is_active' => true,
        ]);
    }

    private function product(Location $branch, float $price, float $stock, float $available, bool $tracked = false): Product
    {
        $category = DB::table('categories')->insertGetId([
            'name' => 'Test', 'slug' => 'category-'.Str::random(6),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = Product::query()->create([
            'category_id' => $category, 'name' => 'Test Product', 'name_ar' => 'صنف اختبار',
            'sku' => 'SKU-'.Str::upper(Str::random(8)), 'base_selling_price' => $price,
            'barcode' => 'BAR-'.Str::upper(Str::random(9)),
            'is_active' => true, 'tracks_batch' => $tracked,
        ]);
        LocationProduct::query()->create([
            'location_id' => $branch->id, 'product_id' => $product->id,
            'is_available' => true, 'local_selling_price' => $price,
        ]);
        Inventory::query()->create([
            'location_id' => $branch->id, 'product_id' => $product->id,
            'quantity' => $stock, 'reserved_quantity' => $stock - $available,
        ]);

        return $product;
    }

    private function method(string $type, bool $verification = false): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => $type, 'name_ar' => $type,
            'code' => 'M-'.Str::upper(Str::random(6)), 'type' => $type,
            'requires_verification' => $verification,
            'requires_reference' => $verification, 'is_active' => true,
        ]);
    }

    private function payload(Location $branch, Employee $employee, Product $product, float $quantity): array
    {
        return [
            'request_key' => (string) Str::uuid(), 'location_id' => $branch->id,
            'employee_id' => $employee->id, 'payment_plan' => 'account',
            'first_due_date' => now()->addMonth()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ];
    }
}
