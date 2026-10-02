<?php

namespace Tests\Feature\Finance;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountingOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_filters_branch_and_counts_all_confirmed_collection_flows_once(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        $first = $this->branch();
        $second = $this->branch();
        $user = $this->userAt($first, ['financial.dashboard.view', 'financial.global.view']);
        $method = $this->method();

        $this->invoice($first, $user, 200, 10, 5, 195, 75);
        $this->invoice($first, $user, 50, 0, 0, 50, 30, now()->subMonths(2));
        $this->invoice($second, $user, 900, 0, 0, 900, 500);
        $this->invoice($first, $user, 20, 0, 0, 20, 20, now(), 'cancelled');

        $paymentId = $this->payment($first, $user, $method, 100, 'confirmed');
        $this->payment($first, $user, $method, 50, 'corrected');
        $this->payment($first, $user, $method, 20, 'refunded');
        $this->payment($first, $user, $method, 10, 'pending_verification');
        $this->payment($second, $user, $method, 300, 'confirmed');

        $customer = Customer::query()->create(['name' => 'Customer', 'phone' => '059'.Str::random(7)]);
        foreach ([[$first, 'confirmed', 40], [$first, 'pending_verification', 15], [$second, 'confirmed', 200]] as [$location, $status, $amount]) {
            DB::table('customer_payments')->insert([
                'customer_id' => $customer->id, 'location_id' => $location->id,
                'payment_method_id' => $method->id, 'amount' => $amount,
                'status' => $status, 'received_by' => $user->id, 'paid_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('refunds')->insert([
            'payment_id' => $paymentId, 'order_type' => 'order', 'order_id' => 999,
            'payment_method_id' => $method->id, 'amount' => 12, 'reason' => 'Test',
            'processed_by' => $user->id, 'processed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('financial.dashboard', ['location_id' => $first->id]));
        $response->assertOk()->assertViewHas('locationId', $first->id)
            ->assertViewHas('data', function ($data): bool {
                return (float) $data['grossSales'] === 200.0
                    && (float) $data['discounts'] === 10.0
                    && (float) $data['taxes'] === 5.0
                    && (float) $data['netSales'] === 195.0
                    && (float) $data['collections'] === 210.0
                    && (float) $data['refunds'] === 12.0
                    && (float) $data['netCollections'] === 198.0
                    && (float) $data['pendingVerification'] === 25.0
                    && (float) $data['outstanding'] === 105.0
                    && $data['invoiceCount'] === 1
                    && $data['cancelledCount'] === 1;
            });
        $this->actingAs($user)->get(route('financial.dashboard.branch', $second))
            ->assertOk()->assertViewHas('locationId', $second->id);
        $this->actingAs($user)->get(route('financial.dashboard'))
            ->assertOk()->assertViewHas('data', fn ($data): bool => (float) $data['collections'] === 710.0);
    }

    public function test_finance_views_enforce_branch_and_separate_ledger_permission(): void
    {
        $first = $this->branch();
        $second = $this->branch();
        $reader = $this->userAt($first, ['financial.dashboard.view', 'financial.reports.view']);
        $dashboardOnly = $this->userAt($first, ['financial.dashboard.view']);

        $this->actingAs($reader)->get(route('financial.dashboard'))->assertOk();
        $this->actingAs($reader)->get(route('financial.dashboard', ['location_id' => $second->id]))->assertForbidden();
        $this->actingAs($reader)->get(route('financial.dashboard.branch', $second))->assertForbidden();
        $this->actingAs($reader)->get(route('accounting.ledger.index', ['location_id' => $second->id]))->assertForbidden();
        $this->actingAs($dashboardOnly)->get(route('accounting.ledger.index'))->assertForbidden();
    }

    public function test_ledger_filters_date_type_and_branch_without_mixing_currency_or_other_locations(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        $first = $this->branch();
        $second = $this->branch();
        $reader = $this->userAt($first, ['financial.reports.view']);
        $global = $this->userAt($first, ['financial.reports.view', 'financial.global.view']);

        $this->entry($first, $reader, 'sale', 'Local sale', 75, 'ILS');
        $this->entry($first, $reader, 'sale', 'Foreign sale', 5, 'USD');
        $this->entry($first, $reader, 'refund', 'Local refund', 12, 'ILS');
        $this->entry($second, $reader, 'expense', 'Other branch private', 120, 'ILS');
        $this->entry($first, $reader, 'sale', 'Older entry', 50, 'ILS', '2026-09-30');

        $this->actingAs($reader)->get(route('accounting.ledger.index'))
            ->assertOk()->assertSee('Local sale')->assertSee('Foreign sale')
            ->assertDontSee('Other branch private')->assertDontSee('Older entry')
            ->assertViewHas('summary', fn ($rows): bool => $rows->count() === 3);

        $this->actingAs($global)->get(route('accounting.ledger.index', [
            'location_id' => $first->id, 'entry_type' => 'sale',
            'date_from' => '2026-10-01', 'date_to' => '2026-10-02',
        ]))->assertOk()->assertSee('Local sale')->assertSee('Foreign sale')
            ->assertDontSee('Local refund')->assertDontSee('Other branch private')
            ->assertViewHas('summary', fn ($rows): bool => $rows->count() === 2);

        $this->actingAs($reader)->get(route('accounting.ledger.index', ['entry_type' => 'invalid']))
            ->assertSessionHasErrors('entry_type');
    }

    private function branch(): Location
    {
        return Location::query()->create([
            'name' => 'Branch '.Str::random(6), 'code' => 'B-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
    }

    private function userAt(Location $branch, array $permissions): User
    {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-'.Str::upper(Str::random(6)),
            'full_name' => 'Accounting Tester', 'employment_status' => 'active',
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

    private function method(): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => 'Cash', 'name_ar' => 'نقد', 'code' => 'C-'.Str::upper(Str::random(6)),
            'type' => 'cash', 'is_active' => true,
        ]);
    }

    private function invoice(Location $branch, User $user, float $subtotal, float $discount, float $tax, float $total, float $remaining, $date = null, string $status = 'active'): void
    {
        DB::table('invoices')->insert([
            'invoice_number' => 'INV-'.Str::upper(Str::random(8)),
            'invoice_type' => 'regular_order', 'order_type' => 'order', 'order_id' => 999,
            'location_id' => $branch->id, 'status' => $status, 'subtotal' => $subtotal,
            'discount_amount' => $discount, 'tax_amount' => $tax, 'total_amount' => $total,
            'paid_amount' => $total - $remaining, 'remaining_amount' => $remaining,
            'issued_by' => $user->id, 'issued_at' => $date ?? now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function payment(Location $branch, User $user, PaymentMethod $method, float $amount, string $status): int
    {
        return DB::table('payments')->insertGetId([
            'order_type' => 'order', 'order_id' => 999, 'payment_method_id' => $method->id,
            'location_id' => $branch->id, 'amount' => $amount, 'status' => $status,
            'received_by' => $user->id, 'paid_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function entry(Location $branch, User $user, string $type, string $description, float $amount, string $currency, string $date = '2026-10-02'): void
    {
        SalesLedgerEntry::query()->create([
            'location_id' => $branch->id, 'entry_type' => $type,
            'entry_date' => $date, 'description' => $description,
            'amount' => $amount, 'currency_code' => $currency,
            'created_by' => $user->id, 'created_at' => now(),
        ]);
    }
}
