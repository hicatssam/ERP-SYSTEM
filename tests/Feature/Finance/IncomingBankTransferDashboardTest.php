<?php

namespace Tests\Feature\Finance;

use App\Models\Employee;
use App\Models\IncomingBankTransfer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IncomingBankTransferDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function branch_user_sees_only_own_branch_transfers(): void
    {
        $branchA = $this->makeLocation('A');
        $branchB = $this->makeLocation('B');
        $user = $this->makeBranchUser($branchA, [
            'financial.branch.view',
        ]);

        [$method, $accountA] = $this->makeMethodAndAccount($branchA);
        [, $accountB] = $this->makeMethodAndAccount($branchB, $method);

        $this->makeTransfer($branchA, $method, $accountA, 'REF-BRANCH-A');
        $this->makeTransfer($branchB, $method, $accountB, 'REF-BRANCH-B');

        $this->actingAs($user)
            ->get(route('incoming-bank-transfers.index'))
            ->assertOk()
            ->assertSee('REF-BRANCH-A')
            ->assertDontSee('REF-BRANCH-B');
    }

    #[Test]
    public function branch_user_cannot_spoof_another_branch_when_recording_transfer(): void
    {
        $branchA = $this->makeLocation('A');
        $branchB = $this->makeLocation('B');

        $user = $this->makeBranchUser($branchA, [
            'payments.record',
        ]);

        [$method, $accountA] = $this->makeMethodAndAccount($branchA);

        $this->actingAs($user)
            ->from(route('incoming-bank-transfers.index'))
            ->post(route('incoming-bank-transfers.store'), [
                'location_id' => $branchB->id,
                'payment_method_id' => $method->id,
                'location_payment_account_id' => $accountA->id,
                'sender_name' => 'محوّل الفرع',
                'sender_phone' => '0599000000',
                'reference_number' => 'REF-SCOPED-1',
                'amount' => 125.50,
            ])
            ->assertRedirect(route('incoming-bank-transfers.index'))
            ->assertSessionHas('success');

        $transfer = IncomingBankTransfer::query()->sole();

        $this->assertSame($branchA->id, $transfer->location_id);
        $this->assertSame('pending_verification', $transfer->statusValue());
    }

    #[Test]
    public function admin_can_filter_all_branches_by_location_method_and_status(): void
    {
        $branchA = $this->makeLocation('A');
        $branchB = $this->makeLocation('B');
        $admin = $this->makeAdmin();

        [$method, $accountA] = $this->makeMethodAndAccount($branchA);
        [, $accountB] = $this->makeMethodAndAccount($branchB, $method);

        $this->makeTransfer(
            $branchA,
            $method,
            $accountA,
            'REF-ADMIN-A',
            'pending_verification'
        );

        $this->makeTransfer(
            $branchB,
            $method,
            $accountB,
            'REF-ADMIN-B',
            'confirmed'
        );

        $this->actingAs($admin)
            ->get(route('incoming-bank-transfers.index', [
                'location_id' => $branchB->id,
                'payment_method_id' => $method->id,
                'status' => 'confirmed',
            ]))
            ->assertOk()
            ->assertSee('REF-ADMIN-B')
            ->assertDontSee('REF-ADMIN-A');
    }

    #[Test]
    public function dashboard_totals_follow_the_current_admin_filters(): void
    {
        $branchA = $this->makeLocation('A');
        $branchB = $this->makeLocation('B');
        $admin = $this->makeAdmin();

        [$method, $accountA] = $this->makeMethodAndAccount($branchA);
        [, $accountB] = $this->makeMethodAndAccount($branchB, $method);

        $this->makeTransfer(
            $branchA,
            $method,
            $accountA,
            'REF-TOTAL-A',
            'pending_verification'
        );

        $this->makeTransfer(
            $branchB,
            $method,
            $accountB,
            'REF-TOTAL-B',
            'confirmed'
        );

        $this->actingAs($admin)
            ->get(route('incoming-bank-transfers.index', [
                'location_id' => $branchB->id,
                'status' => 'confirmed',
            ]))
            ->assertOk()
            ->assertViewHas(
                'summary',
                fn ($summary): bool =>
                    (int) $summary->transfers_count === 1
                    && (float) $summary->total_amount === 100.0
                    && (float) $summary->confirmed_total === 100.0
                    && (float) $summary->pending_total === 0.0
            );
    }

    #[Test]
    public function branch_user_cannot_export_another_branch_transfers(): void
    {
        $branchA = $this->makeLocation('A');
        $branchB = $this->makeLocation('B');

        $user = $this->makeBranchUser($branchA, [
            'financial.branch.view',
        ]);

        [$method, $accountB] = $this->makeMethodAndAccount($branchB);

        $this->makeTransfer(
            $branchB,
            $method,
            $accountB,
            'REF-PRIVATE-B'
        );

        $this->actingAs($user)
            ->get(route('incoming-bank-transfers.export.xlsx', [
                'location_id' => $branchB->id,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('incoming-bank-transfers.export.pdf', [
                'location_id' => $branchB->id,
            ]))
            ->assertForbidden();
    }

    #[Test]
    public function transfer_exports_download_real_excel_and_pdf_files(): void
    {
        $branch = $this->makeLocation('A');
        $admin = $this->makeAdmin();

        [$method, $account] = $this->makeMethodAndAccount($branch);

        $this->makeTransfer(
            $branch,
            $method,
            $account,
            'REF-EXPORT-1',
            'confirmed'
        );

        $xlsxResponse = $this->actingAs($admin)
            ->get(route('incoming-bank-transfers.export.xlsx', [
                'location_id' => $branch->id,
                'payment_method_id' => $method->id,
                'status' => 'confirmed',
            ]));

        $xlsxResponse->assertOk();

        $this->assertStringContainsString(
            '.xlsx',
            (string) $xlsxResponse->headers->get(
                'content-disposition'
            )
        );

        $pdfResponse = $this->actingAs($admin)
            ->get(route('incoming-bank-transfers.export.pdf', [
                'location_id' => $branch->id,
                'payment_method_id' => $method->id,
                'status' => 'confirmed',
            ]));

        $pdfResponse
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/pdf'
            );

        $this->assertStringContainsString(
            '.pdf',
            (string) $pdfResponse->headers->get(
                'content-disposition'
            )
        );
    }

    #[Test]
    public function verifier_can_approve_transfer_from_own_branch(): void
    {
        $branch = $this->makeLocation('A');
        $user = $this->makeBranchUser($branch, [
            'financial.branch.view',
            'payments.verify',
        ]);

        [$method, $account] = $this->makeMethodAndAccount($branch);

        $transfer = $this->makeTransfer(
            $branch,
            $method,
            $account,
            'REF-VERIFY-1'
        );

        $this->actingAs($user)
            ->from(route('incoming-bank-transfers.index'))
            ->post(route('incoming-bank-transfers.verify', $transfer), [
                'action' => 'verify',
            ])
            ->assertRedirect(route('incoming-bank-transfers.index'))
            ->assertSessionHas('success');

        $transfer->refresh();

        $this->assertSame('confirmed', $transfer->statusValue());
        $this->assertSame($user->id, $transfer->verified_by);
        $this->assertNotNull($transfer->verified_at);
    }

    private function makeLocation(string $suffix): Location
    {
        return Location::query()->create([
            'name' => 'Incoming Transfer Branch ' . $suffix,
            'code' => 'IT-' . $suffix . '-' . Str::upper(Str::random(4)),
            'type' => 'branch',
            'is_active' => true,
        ]);
    }

    private function makeBranchUser(
        Location $location,
        array $permissions
    ): User {
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-' . Str::upper(Str::random(6)),
            'full_name' => 'Finance ' . Str::random(5),
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

        foreach ($permissions as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            $user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $role = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        foreach (['payments.record', 'payments.verify'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function makeMethodAndAccount(
        Location $location,
        ?PaymentMethod $method = null
    ): array {
        $method ??= PaymentMethod::query()->create([
            'name' => 'Bank Transfer',
            'name_ar' => 'تحويل بنكي',
            'code' => 'incoming-bank-' . Str::lower(Str::random(6)),
            'type' => 'bank_transfer',
            'requires_verification' => false,
            'requires_reference' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $account = LocationPaymentAccount::query()->create([
            'location_id' => $location->id,
            'payment_method_id' => $method->id,
            'name' => 'Account ' . $location->code,
            'provider_name' => 'Test Bank',
            'account_holder_name' => 'Dahab Sweets',
            'account_number' => 'ACC-' . Str::upper(Str::random(8)),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$method, $account];
    }

    private function makeTransfer(
        Location $location,
        PaymentMethod $method,
        LocationPaymentAccount $account,
        string $reference,
        string $status = 'pending_verification'
    ): IncomingBankTransfer {
        $creator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        return IncomingBankTransfer::query()->create([
            'location_id' => $location->id,
            'payment_method_id' => $method->id,
            'location_payment_account_id' => $account->id,
            'sender_name' => 'Test Sender',
            'sender_phone' => '0599000000',
            'reference_number' => $reference,
            'amount' => 100,
            'currency_code' => 'ILS',
            'status' => $status,
            'received_at' => now(),
            'created_by' => $creator->id,
        ]);
    }
}
