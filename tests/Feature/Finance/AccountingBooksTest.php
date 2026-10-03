<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingVoucher;
use App\Models\Currency;
use App\Models\DailyCashReconciliation;
use App\Models\Employee;
use App\Models\FinancialPeriod;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\AccountingReportService;
use App\Services\Finance\DailyCashReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountingBooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_cash_receipt_posts_balanced_entry_once_and_reverses_before_cash_close(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $cash = $this->method('cash');
        $request = $this->voucher($branch, $cash, 'receipt', '100.00', 'MISC-001');

        $this->actingAs($user)->post(route('accounting.books.vouchers.store'), $request)
            ->assertRedirect()->assertSessionHasNoErrors();
        $draft = AccountingVoucher::query()->sole();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(0, AccountingJournal::query()->count());
        $this->assertEquals(0, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, today()->toDateString())['components']['voucher_receipts']);

        $this->actingAs($user)->post(route('accounting.books.vouchers.store'), $request)
            ->assertSessionHasNoErrors();
        $this->assertSame(1, AccountingVoucher::query()->count());
        $this->actingAs($user)->post(route('accounting.books.vouchers.store'),
            $this->voucher($branch, $cash, 'receipt', '100.00', 'MISC-001'))
            ->assertSessionHasErrors('external_reference');

        $this->actingAs($user)->post(route('accounting.books.vouchers.post', $draft))
            ->assertRedirect()->assertSessionHasNoErrors();
        $journal = AccountingJournal::query()->sole();
        $this->assertEquals(100, $journal->total_debit);
        $this->assertEquals(100, $journal->total_credit);
        $this->assertSame(2, $journal->lines()->count());
        $this->assertEquals(100, $journal->lines()->whereHas('account', fn ($q) => $q->where('code', '1000'))->value('debit'));
        $this->assertEquals(100, $journal->lines()->whereHas('account', fn ($q) => $q->where('code', '4100'))->value('credit'));
        $report = app(AccountingReportService::class)->report([$branch->id], '2026-10-01', '2026-10-02');
        $this->assertSame(10000, $report['income']['net']);
        $this->assertSame(0, $report['position']['difference']);
        $this->assertEquals(100, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, today()->toDateString())['components']['voucher_receipts']);
        $this->actingAs($user)->post(route('accounting.books.vouchers.post', $draft))
            ->assertSessionHasErrors('voucher');

        $this->actingAs($user)->post(route('accounting.books.vouchers.reverse', $draft), ['reason' => 'خطأ في المبلغ'])
            ->assertSessionHasNoErrors();
        $this->assertSame('reversed', $draft->fresh()->status);
        $this->assertSame(2, AccountingJournal::query()->count());
        $this->assertEquals(0, app(AccountingReportService::class)
            ->report([$branch->id], '2026-10-01', '2026-10-02')['position']['difference']);
        $this->assertSame(0, app(AccountingReportService::class)
            ->report([$branch->id], '2026-10-01', '2026-10-02')['income']['net']);
        $this->assertEquals(0, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, today()->toDateString())['components']['voucher_receipts']);
        $this->actingAs($user)->post(route('accounting.books.vouchers.reverse', $draft), ['reason' => 'كرر العكس'])
            ->assertSessionHasErrors('voucher');
    }

    public function test_cash_payment_is_counted_as_outflow_and_closed_cash_or_period_blocks_posting(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $period = $this->openPeriod($user);
        $cash = $this->method('cash');
        $payload = $this->voucher($branch, $cash, 'payment', '25.50', 'PAY-001');
        $payload['counter_account_id'] = $this->account('6000')->id;
        $this->actingAs($user)->post(route('accounting.books.vouchers.store'), $payload)->assertSessionHasNoErrors();
        $voucher = AccountingVoucher::query()->sole();

        $period->update(['status' => 'closed']);
        $this->actingAs($user)->post(route('accounting.books.vouchers.post', $voucher))
            ->assertSessionHasErrors('financial_period');
        $this->assertSame('draft', $voucher->fresh()->status);
        $this->assertSame(0, AccountingJournal::query()->count());
        $period->update(['status' => 'open']);
        $this->actingAs($user)->post(route('accounting.books.vouchers.post', $voucher))->assertSessionHasNoErrors();
        $this->assertEquals(25.50, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, today()->toDateString())['components']['voucher_payments']);
        $report = app(AccountingReportService::class)->report([$branch->id], '2026-10-01', '2026-10-02');
        $this->assertSame(2550, $report['income']['expenses']);
        $this->assertSame(-2550, $report['income']['net']);
        $this->assertSame(0, $report['position']['difference']);

        $second = $this->voucher($branch, $cash, 'receipt', '10', 'MISC-002');
        $this->actingAs($user)->post(route('accounting.books.vouchers.store'), $second)->assertSessionHasNoErrors();
        $pending = AccountingVoucher::query()->where('external_reference', 'MISC-002')->firstOrFail();
        DailyCashReconciliation::query()->create([
            'location_id' => $branch->id, 'business_date' => today()->toDateString(),
            'opening_balance' => 100, 'expected_closing' => 74.50,
            'actual_closing' => 74.50, 'variance' => 0,
            'closed_by' => $user->id, 'closed_at' => now(),
        ]);
        $this->actingAs($user)->post(route('accounting.books.vouchers.post', $pending))
            ->assertSessionHasErrors('voucher');
        $this->actingAs($user)->post(route('accounting.books.vouchers.reverse', $voucher),
            ['reason' => 'بعد الإقفال'])->assertSessionHasErrors('voucher');
        $this->assertSame(1, AccountingJournal::query()->count());
    }

    public function test_bank_proof_and_branch_permissions_protect_vouchers_and_reports(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        Storage::fake('local');
        [$branch, $creator] = $this->branchUser(['accounting.books.view', 'accounting.vouchers.create']);
        [$other, $outsider] = $this->branchUser(['accounting.books.view', 'accounting.vouchers.create', 'accounting.reports.view']);
        $this->baseCurrency();
        $this->openPeriod($creator);
        $bank = $this->method('bank_transfer', true);
        $payload = $this->voucher($branch, $bank, 'receipt', '90', 'BNK-001');
        $payload['treasury_account_id'] = $this->account('1010')->id;

        $this->actingAs($creator)->post(route('accounting.books.vouchers.store'), $payload)
            ->assertSessionHasErrors('payment_proof');
        $payload['payment_proof'] = UploadedFile::fake()->image('proof.png');
        $this->actingAs($creator)->post(route('accounting.books.vouchers.store'), $payload)
            ->assertSessionHasNoErrors();
        $voucher = AccountingVoucher::query()->sole();
        $this->actingAs($creator)->post(route('accounting.books.vouchers.post', $voucher))->assertForbidden();
        $this->actingAs($creator)->get(route('accounting.books.statements'))->assertForbidden();
        $this->actingAs($outsider)->get(route('accounting.books.vouchers.show', $voucher))->assertForbidden();
        $this->actingAs($outsider)->get(route('accounting.books.vouchers.proof', $voucher))->assertForbidden();
        unset($payload['payment_proof']);
        $this->actingAs($outsider)->post(route('accounting.books.vouchers.store', $payload))->assertForbidden();
        $this->actingAs($creator)->get(route('accounting.books.vouchers.proof', $voucher))->assertOk();

        [$sameBranch, $approver] = $this->branchUser(['accounting.books.view', 'accounting.vouchers.post']);
        $approver->employee->locations()->detach();
        $approver->employee->locations()->attach($branch->id, ['is_primary' => true]);
        $this->actingAs($approver)->post(route('accounting.books.vouchers.post', $voucher))
            ->assertSessionHasNoErrors();
        $this->assertEquals(0, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, today()->toDateString())['components']['voucher_receipts']);
    }

    public function test_manual_journal_rejects_unbalanced_or_cash_lines_and_reports_opening_and_reversal(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $data = [
            'request_key' => (string) Str::uuid(), 'location_id' => $branch->id,
            'entry_date' => '2026-10-02', 'description' => 'تسوية رصيد افتتاحي',
            'lines' => [
                ['account_id' => $this->account('1100')->id, 'debit' => 80, 'credit' => 0],
                ['account_id' => $this->account('3000')->id, 'debit' => 0, 'credit' => 79],
            ],
        ];
        $this->actingAs($user)->post(route('accounting.books.journals.store'), $data)
            ->assertSessionHasErrors('lines');
        $this->assertSame(0, AccountingJournal::query()->count());
        $data['lines'][1]['credit'] = 80;
        $data['lines'][0]['account_id'] = $this->account('1000')->id;
        $this->actingAs($user)->post(route('accounting.books.journals.store'), $data)
            ->assertSessionHasErrors('lines');
        $data['lines'][0]['account_id'] = $this->account('1100')->id;
        $this->actingAs($user)->post(route('accounting.books.journals.store'), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $original = AccountingJournal::query()->sole();
        $this->actingAs($user)->post(route('accounting.books.journals.store'), $data)
            ->assertSessionHasNoErrors();
        $this->assertSame(1, AccountingJournal::query()->count());
        $report = app(AccountingReportService::class)->report([$branch->id], '2026-10-03', '2026-10-03');
        $this->assertSame(8000, $report['trial']['opening_debit']);
        $this->assertSame(8000, $report['trial']['opening_credit']);
        $this->assertSame(0, $report['position']['difference']);
        $this->actingAs($user)->post(route('accounting.books.journals.reverse', $original),
            ['reason' => 'تسوية خاطئة'])->assertSessionHasNoErrors();
        $this->assertSame(2, AccountingJournal::query()->count());
        $this->actingAs($user)->post(route('accounting.books.journals.reverse', $original),
            ['reason' => 'عكس مكرر'])->assertSessionHasErrors('journal');
        $this->assertEquals(0, app(AccountingReportService::class)
            ->report([$branch->id], '2026-10-01', '2026-10-02')['trial']['closing_debit']);
    }

    private function allPermissions(): array
    {
        return [
            'accounting.books.view', 'accounting.vouchers.create', 'accounting.vouchers.post',
            'accounting.vouchers.reverse', 'accounting.journals.post', 'accounting.reports.view',
        ];
    }

    private function branchUser(array $permissions): array
    {
        $branch = Location::query()->create([
            'name' => 'Branch '.Str::random(5), 'code' => 'B-'.Str::upper(Str::random(6)),
            'type' => 'branch', 'is_active' => true,
        ]);
        $employee = Employee::query()->create([
            'employee_number' => 'EMP-'.Str::upper(Str::random(6)),
            'full_name' => 'Book Tester', 'employment_status' => 'active',
        ]);
        $employee->locations()->attach($branch->id, ['is_primary' => true]);
        $user = User::factory()->create([
            'employee_id' => $employee->id, 'is_active' => true, 'must_change_password' => false,
        ]);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$branch, $user];
    }

    private function baseCurrency(): Currency
    {
        return Currency::query()->create([
            'code' => 'ILS', 'name' => 'Shekel', 'is_base' => true, 'is_active' => true,
        ]);
    }

    private function openPeriod(User $user): FinancialPeriod
    {
        return FinancialPeriod::query()->create([
            'name' => 'October 2026', 'year' => 2026, 'month' => 10,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
            'status' => 'open', 'opened_by' => $user->id, 'opened_at' => now(),
        ]);
    }

    private function method(string $type, bool $requiresProof = false): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => $type, 'name_ar' => $type, 'code' => 'M-'.Str::upper(Str::random(6)),
            'type' => $type, 'is_active' => true,
            'requires_verification' => $requiresProof, 'requires_reference' => $requiresProof,
        ]);
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::query()->where('code', $code)->firstOrFail();
    }

    private function voucher(Location $branch, PaymentMethod $method, string $type, string $amount, string $ref): array
    {
        return [
            'request_key' => (string) Str::uuid(), 'type' => $type,
            'location_id' => $branch->id, 'voucher_date' => '2026-10-02',
            'payment_method_id' => $method->id, 'treasury_account_id' => $this->account('1000')->id,
            'counter_account_id' => $this->account('4100')->id,
            'amount' => $amount, 'party_name' => 'Outside party',
            'external_reference' => $ref, 'description' => 'Miscellaneous cash movement',
        ];
    }
}
