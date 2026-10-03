<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingVoucher;
use App\Models\Currency;
use App\Models\DailyCashReconciliation;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialPeriod;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\SalesLedgerEntry;
use App\Models\User;
use App\Services\Finance\AccountingReportService;
use App\Services\Finance\DailyCashReconciliationService;
use App\Services\Finance\FinancialPostingService;
use App\Services\Finance\ExpenseWorkflowService;
use App\Services\Finance\AccountingBookService;
use App\Services\Finance\OperationalJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountingBooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_invoice_and_confirmed_cash_collection_post_once_into_the_same_balanced_ledger(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $cash = $this->method('cash');
        $invoice = Invoice::query()->create([
            'invoice_number' => 'GL-TEST-001', 'invoice_type' => 'regular_order',
            'order_type' => 'order', 'order_id' => 98701, 'location_id' => $branch->id,
            'status' => 'active', 'subtotal' => 120, 'total_amount' => 120,
            'issued_by' => $user->id, 'issued_at' => now(),
        ]);
        $posting = app(FinancialPostingService::class);
        $posting->sale($invoice, $user);
        $posting->sale($invoice, $user);
        $invoice->total_amount = 121;
        try {
            $posting->sale($invoice, $user);
            $this->fail('A changed invoice must not silently reuse the old journal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $invoice->total_amount = 120;

        $payment = Payment::query()->create([
            'order_type' => 'order', 'order_id' => $invoice->order_id,
            'location_id' => $branch->id, 'payment_method_id' => $cash->id,
            'amount' => 30, 'status' => 'confirmed', 'paid_at' => now(),
            'received_by' => $user->id,
        ]);
        $posting->collection($payment, $user);
        $posting->collection($payment, $user);

        $this->assertSame(2, AccountingJournal::query()->count());
        $this->assertEquals(120, AccountingJournal::query()->where('kind', 'operational')->firstOrFail()->total_debit);
        $report = app(AccountingReportService::class)->report([$branch->id], '2026-10-01', '2026-10-02');
        $this->assertSame(0, $report['unlinked_operations']);
        $this->assertSame(12000, $report['income']['net']);
        $this->assertSame(12000, $report['position']['assets']);
        $this->assertSame(0, $report['position']['difference']);
        $this->assertEquals(30, AccountingJournal::query()->where('kind', 'operational')->latest('id')
            ->firstOrFail()->lines()->whereHas('account', fn ($q) => $q->where('code', '1000'))->value('debit'));
    }

    public function test_expense_and_void_use_opposite_accounts_and_refund_uses_its_own_method(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $category = ExpenseCategory::query()->create(['code' => 'TEST-OPS', 'name' => 'تشغيل']);
        $expense = Expense::query()->create([
            'expense_number' => 'EXP-GL-001', 'expense_category_id' => $category->id,
            'location_id' => $branch->id, 'amount' => 25, 'expense_date' => today(),
            'description' => 'مصروف آجل', 'status' => 'approved', 'created_by' => $user->id,
        ]);
        $workflow = app(ExpenseWorkflowService::class);
        $workflow->post($expense, $user);
        $workflow->post($expense, $user);
        $this->assertSame(1, AccountingJournal::query()->count());
        $this->assertEquals(25, AccountingJournal::query()->firstOrFail()->lines()
            ->whereHas('account', fn ($q) => $q->where('code', '2200'))->value('credit'));
        $workflow->void($expense, 'تصحيح', $user);
        $this->assertSame(2, AccountingJournal::query()->count());
        $this->assertSame(0, app(AccountingReportService::class)
            ->report([$branch->id], '2026-10-01', '2026-10-02')['income']['expenses']);

        $cash = $this->method('cash');
        $bank = $this->method('bank_transfer');
        $payment = Payment::query()->create([
            'order_type' => 'order', 'order_id' => 90871, 'location_id' => $branch->id,
            'payment_method_id' => $cash->id, 'amount' => 10,
            'status' => 'confirmed', 'paid_at' => now(), 'received_by' => $user->id,
        ]);
        $refund = Refund::query()->create([
            'payment_id' => $payment->id, 'order_type' => 'order', 'order_id' => 90871,
            'payment_method_id' => $bank->id, 'amount' => 5,
            'reason' => 'استرداد بنكي', 'processed_by' => $user->id, 'processed_at' => now(),
        ]);
        app(FinancialPostingService::class)->refund($refund, $payment, $user);
        $this->assertEquals(5, AccountingJournal::query()->latest('id')->firstOrFail()->lines()
            ->whereHas('account', fn ($q) => $q->where('code', '1010'))->value('credit'));
    }

    public function test_failed_journal_rolls_back_the_operational_ledger_entry(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $this->account('1100')->update(['is_active' => false]);
        $invoice = Invoice::query()->create([
            'invoice_number' => 'GL-TEST-ROLLBACK', 'invoice_type' => 'regular_order',
            'order_type' => 'order', 'order_id' => 98702, 'location_id' => $branch->id,
            'status' => 'active', 'subtotal' => 20, 'total_amount' => 20,
            'issued_by' => $user->id, 'issued_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        try {
            app(FinancialPostingService::class)->sale($invoice, $user);
        } finally {
            $this->assertSame(0, SalesLedgerEntry::query()->count());
            $this->assertSame(0, AccountingJournal::query()->count());
        }
    }

    public function test_retry_after_period_close_is_idempotent_but_a_new_post_is_rejected(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $period = $this->openPeriod($user);
        $first = Invoice::query()->create([
            'invoice_number' => 'GL-CLOSE-1', 'invoice_type' => 'regular_order',
            'order_type' => 'order', 'order_id' => 98703, 'location_id' => $branch->id,
            'status' => 'active', 'subtotal' => 17, 'total_amount' => 17,
            'issued_by' => $user->id, 'issued_at' => now(),
        ]);
        $posting = app(FinancialPostingService::class);
        $posting->sale($first, $user);
        $period->update(['status' => 'closed']);
        $posting->sale($first, $user);
        $this->assertSame(1, AccountingJournal::query()->count());

        $second = $first->replicate();
        $second->invoice_number = 'GL-CLOSE-2';
        $second->order_id = 98704;
        $second->save();
        try {
            $posting->sale($second, $user);
            $this->fail('A new journal in a closed period must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('financial_period', $exception->errors());
        }
        $this->assertSame(1, AccountingJournal::query()->count());
        $this->assertSame(1, SalesLedgerEntry::query()->count());
    }

    public function test_corrections_and_refund_keep_the_trial_balance_and_cash_bank_separate(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $cash = $this->method('cash');
        $bank = $this->method('bank_transfer');
        $invoice = Invoice::query()->create([
            'invoice_number' => 'GL-MIXED-1', 'invoice_type' => 'regular_order',
            'order_type' => 'order', 'order_id' => 98705, 'location_id' => $branch->id,
            'status' => 'active', 'subtotal' => 100, 'total_amount' => 100,
            'issued_by' => $user->id, 'issued_at' => now(),
        ]);
        $posting = app(FinancialPostingService::class);
        $posting->sale($invoice, $user);
        $payment = Payment::query()->create([
            'order_type' => 'order', 'order_id' => $invoice->order_id,
            'location_id' => $branch->id, 'payment_method_id' => $cash->id,
            'amount' => 40, 'status' => 'confirmed', 'paid_at' => now(), 'received_by' => $user->id,
        ]);
        $posting->collection($payment, $user);
        $posting->collection($payment, $user, 'correction-1', 10);
        $posting->paymentReversal($payment, $user, 'correction-2', 3);
        $refund = Refund::query()->create([
            'payment_id' => $payment->id, 'order_type' => 'order', 'order_id' => $invoice->order_id,
            'payment_method_id' => $bank->id, 'amount' => 5, 'reason' => 'جزئي',
            'processed_by' => $user->id, 'processed_at' => now(),
        ]);
        $posting->refund($refund, $payment, $user);
        $posting->refund($refund, $payment, $user);

        $report = app(AccountingReportService::class)->report([$branch->id], '2026-10-02', '2026-10-02');
        $balances = collect($report['rows'])->mapWithKeys(fn ($row) => [
            $row['account']->code => $row['closing_debit'] - $row['closing_credit'],
        ]);
        $this->assertSame(5, AccountingJournal::query()->count());
        $this->assertSame(0, $report['unlinked_operations']);
        $this->assertSame(10000, $report['income']['net']);
        $this->assertSame(5800, $balances['1100']);
        $this->assertSame(4700, $balances['1000']);
        $this->assertSame(-500, $balances['1010']);
        $this->assertSame($report['trial']['closing_debit'], $report['trial']['closing_credit']);
        $this->assertSame(0, $report['position']['difference']);
    }

    public function test_reports_scope_legacy_gaps_and_reversal_across_date_and_branch(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        [$other] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $journal = app(AccountingBookService::class)->createManualJournal([
            'request_key' => (string) Str::uuid(), 'location_id' => $branch->id,
            'entry_date' => '2026-10-02', 'description' => 'تسوية مبيعات الفرع',
            'lines' => [
                ['account_id' => $this->account('1100')->id, 'debit' => '12.35', 'credit' => 0],
                ['account_id' => $this->account('4000')->id, 'debit' => 0, 'credit' => '12.35'],
            ],
        ], $user);
        SalesLedgerEntry::query()->create([
            'location_id' => $branch->id, 'entry_date' => '2026-10-02',
            'entry_type' => 'sale', 'amount' => 7, 'currency_code' => 'ILS',
            'idempotency_key' => 'historical-unlinked', 'created_by' => $user->id,
        ]);
        $first = app(AccountingReportService::class)->report([$branch->id], '2026-10-02', '2026-10-02');
        $this->assertSame(1235, $first['income']['net']);
        $this->assertSame(1, $first['unlinked_operations']);
        $this->assertSame(0, app(AccountingReportService::class)
            ->report([$other->id], '2026-10-02', '2026-10-02')['unlinked_operations']);

        $this->travelTo(now()->setDate(2026, 10, 3)->setTime(10, 0));
        app(AccountingBookService::class)->reverseJournal($journal, 'تسوية خاطئة', $user);
        $second = app(AccountingReportService::class)->report([$branch->id], '2026-10-03', '2026-10-03');
        $this->assertSame(1235, $second['trial']['opening_debit']);
        $this->assertSame(-1235, $second['income']['net']);
        $this->assertSame(0, $second['trial']['closing_debit']);
        $this->assertSame(1, $second['unlinked_operations']);
    }

    public function test_cash_correction_on_next_day_does_not_rewrite_original_closing(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $cash = $this->method('cash');
        $payment = Payment::query()->create([
            'order_type' => 'order', 'order_id' => 98706, 'location_id' => $branch->id,
            'payment_method_id' => $cash->id, 'amount' => 40,
            'status' => 'confirmed', 'paid_at' => now(), 'received_by' => $user->id,
        ]);
        $posting = app(FinancialPostingService::class);
        $posting->collection($payment, $user);
        $this->assertEquals(40, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, '2026-10-02')['components']['sales']);

        $this->travelTo(now()->setDate(2026, 10, 3)->setTime(10, 0));
        PaymentCorrection::query()->create([
            'original_payment_id' => $payment->id, 'original_amount' => 40,
            'corrected_amount' => 45, 'reason' => 'فرق خمسة', 'corrected_by' => $user->id,
        ]);
        $payment->update(['amount' => 45, 'status' => 'corrected']);
        $posting->collection($payment, $user, 'correction-1', 5);
        $this->assertEquals(40, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, '2026-10-02')['components']['sales']);
        $this->assertEquals(5, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, '2026-10-03')['components']['sales']);
        $this->assertSame('2026-10-03', AccountingJournal::query()->latest('id')->firstOrFail()->entry_date->toDateString());

        PaymentCorrection::query()->create([
            'original_payment_id' => $payment->id, 'original_amount' => 45,
            'corrected_amount' => 43, 'reason' => 'تسوية سالب اثنين', 'corrected_by' => $user->id,
        ]);
        $payment->update(['amount' => 43]);
        $posting->paymentReversal($payment, $user, 'correction-2', 2);
        $this->assertEquals(40, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, '2026-10-02')['components']['sales']);
        $this->assertEquals(3, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, '2026-10-03')['components']['sales']);
        $this->assertSame(0, app(AccountingReportService::class)
            ->report([$branch->id], '2026-10-02', '2026-10-03')['position']['difference']);
    }

    public function test_voiding_paid_expense_keeps_cash_outflow_until_actual_recovery(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->setTime(15, 0));
        [$branch, $user] = $this->branchUser($this->allPermissions());
        $this->baseCurrency();
        $this->openPeriod($user);
        $cash = $this->method('cash');
        $category = ExpenseCategory::query()->create(['code' => 'TEST-CASH', 'name' => 'مصروف نقدي']);
        $expense = Expense::query()->create([
            'expense_number' => 'EXP-GL-CASH', 'expense_category_id' => $category->id,
            'payment_method_id' => $cash->id, 'location_id' => $branch->id,
            'amount' => 20, 'expense_date' => today(), 'description' => 'مصروف نقدي',
            'status' => 'approved', 'created_by' => $user->id,
        ]);
        $workflow = app(ExpenseWorkflowService::class);
        $workflow->post($expense, $user);
        $workflow->void($expense, 'لم يرجع المال بعد', $user);

        $report = app(AccountingReportService::class)->report([$branch->id], '2026-10-02', '2026-10-02');
        $balances = collect($report['rows'])->mapWithKeys(fn ($row) => [
            $row['account']->code => $row['closing_debit'] - $row['closing_credit'],
        ]);
        $this->assertSame(-2000, $balances['1000']);
        $this->assertSame(2000, $balances['1160']);
        $this->assertSame(0, $report['income']['net']);
        $this->assertSame(0, $report['position']['difference']);
        $this->assertEquals(20, app(DailyCashReconciliationService::class)
            ->forDay($branch->id, '2026-10-02')['components']['expenses']);
    }

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
