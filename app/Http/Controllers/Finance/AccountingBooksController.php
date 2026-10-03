<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingVoucher;
use App\Models\Currency;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Services\Finance\AccountingBookService;
use App\Services\Finance\AccountingReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingBooksController extends Controller
{
    public function __construct(
        private readonly AccountingBookService $book,
        private readonly AccountingReportService $reports,
    ) {}

    public function accounts(Request $request): View
    {
        return view('finance.books.accounts', [
            'accounts' => AccountingAccount::query()->orderBy('code')->get(),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9.\-]{2,30}$/', 'unique:accounting_accounts,code'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'subtype' => ['nullable', Rule::in(['cash', 'bank'])],
        ]);
        if (isset($data['subtype']) && $data['type'] !== 'asset') {
            return back()->withErrors(['subtype' => 'حساب النقد أو البنك يجب أن يكون أصلًا.'])->withInput();
        }
        AccountingAccount::query()->create($data + ['is_active' => true, 'is_system' => false]);

        return redirect()->route('accounting.books.accounts')->with('success', 'تمت إضافة الحساب إلى الدليل.');
    }

    public function vouchers(Request $request): View
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'type' => ['nullable', Rule::in(['receipt', 'payment'])],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'reversed', 'cancelled'])],
        ]);
        $locationId = $this->locationId($request, $filters['location_id'] ?? null);
        $query = AccountingVoucher::query()->whereIn('location_id', $this->locationIds($locationId));
        if (isset($filters['type'])) $query->where('type', $filters['type']);
        if (isset($filters['status'])) $query->where('status', $filters['status']);

        return view('finance.books.vouchers', [
            'vouchers' => $query->with(['location', 'creator', 'currency'])
                ->latest('id')->paginate(25)->withQueryString(),
            'locations' => $this->locations($request), 'locationId' => $locationId,
        ]);
    }

    public function createVoucher(Request $request): View
    {
        $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id']]);
        $locationId = $this->locationId($request, $request->integer('location_id') ?: null);

        return view('finance.books.voucher-create', [
            'locations' => $this->locations($request), 'locationId' => $locationId,
            'accounts' => AccountingAccount::query()->where('is_active', true)->orderBy('code')->get(),
            'methods' => PaymentMethod::query()->active()->orderBy('sort_order')->get(),
            'currency' => Currency::query()->where('is_base', true)->where('is_active', true)->first(),
        ]);
    }

    public function storeVoucher(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'type' => ['required', Rule::in(['receipt', 'payment'])],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'treasury_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'counter_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'voucher_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            'party_name' => ['required', 'string', 'max:160'],
            'external_reference' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:5', 'max:500'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $this->locationId($request, $data['location_id']);
        $voucher = $this->book->createVoucher($data, $request->file('payment_proof'), $request->user());

        return redirect()->route('accounting.books.vouchers.show', $voucher)
            ->with('success', 'تم حفظ السند مسودة. يحتاج ترحيلًا قبل دخوله في الميزان والصندوق.');
    }

    public function showVoucher(Request $request, AccountingVoucher $voucher): View
    {
        $this->assertLocation($request, $voucher->location_id);

        return view('finance.books.voucher-show', [
            'voucher' => $voucher->load([
                'location', 'paymentMethod', 'treasuryAccount', 'counterAccount', 'currency',
                'creator', 'poster', 'journal.lines.account', 'reversalJournal',
            ]),
        ]);
    }

    public function postVoucher(Request $request, AccountingVoucher $voucher): RedirectResponse
    {
        $this->assertLocation($request, $voucher->location_id);
        $this->book->postVoucher($voucher, $request->user());

        return back()->with('success', 'تم ترحيل السند بقيد متوازن.');
    }

    public function cancelVoucher(Request $request, AccountingVoucher $voucher): RedirectResponse
    {
        $this->assertLocation($request, $voucher->location_id);
        abort_unless($voucher->created_by === $request->user()->id
            || $request->user()->can('accounting.vouchers.post'), 403);
        $this->book->cancelVoucher($voucher, $request->user());

        return back()->with('success', 'ألغيت المسودة دون أثر محاسبي.');
    }

    public function reverseVoucher(Request $request, AccountingVoucher $voucher): RedirectResponse
    {
        $this->assertLocation($request, $voucher->location_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->book->reverseVoucher($voucher, $data['reason'], $request->user());

        return back()->with('success', 'تم عكس السند بقيد مقابل دون حذف الأصل.');
    }

    public function voucherProof(Request $request, AccountingVoucher $voucher): StreamedResponse
    {
        $this->assertLocation($request, $voucher->location_id);
        abort_unless($voucher->payment_proof && Storage::disk('local')->exists($voucher->payment_proof), 404);

        return Storage::disk('local')->download($voucher->payment_proof);
    }

    public function journals(Request $request): View
    {
        $data = $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id']]);
        $locationId = $this->locationId($request, $data['location_id'] ?? null);

        return view('finance.books.journals', [
            'journals' => AccountingJournal::query()->whereIn('location_id', $this->locationIds($locationId))
                ->with(['location', 'creator'])->orderByDesc('entry_date')->orderByDesc('id')
                ->paginate(30)->withQueryString(),
            'locations' => $this->locations($request), 'locationId' => $locationId,
        ]);
    }

    public function createJournal(Request $request): View
    {
        $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id']]);
        $locationId = $this->locationId($request, $request->integer('location_id') ?: null);

        return view('finance.books.journal-create', [
            'locations' => $this->locations($request), 'locationId' => $locationId,
            'accounts' => AccountingAccount::query()->where('is_active', true)
                ->whereNull('subtype')->orderBy('code')->get(),
        ]);
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'entry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'min:5', 'max:500'],
            'lines' => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);
        $this->locationId($request, $data['location_id']);
        $journal = $this->book->createManualJournal($data, $request->user());

        return redirect()->route('accounting.books.journals.show', $journal)
            ->with('success', 'تم ترحيل القيد المتوازن.');
    }

    public function showJournal(Request $request, AccountingJournal $journal): View
    {
        $this->assertLocation($request, $journal->location_id);

        return view('finance.books.journal-show', [
            'journal' => $journal->load(['lines.account', 'location', 'period', 'creator', 'original', 'reversal']),
        ]);
    }

    public function reverseJournal(Request $request, AccountingJournal $journal): RedirectResponse
    {
        $this->assertLocation($request, $journal->location_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $reversal = $this->book->reverseJournal($journal, $data['reason'], $request->user());

        return redirect()->route('accounting.books.journals.show', $reversal)
            ->with('success', 'تم ترحيل قيد عكسي.');
    }

    public function statements(Request $request): View
    {
        $data = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ]);
        $locationId = $this->locationId($request, $data['location_id'] ?? null);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        if ($from > $to) {
            return back()->withErrors(['from' => 'تاريخ البداية بعد تاريخ النهاية.']);
        }

        return view('finance.books.statements', [
            'report' => $this->reports->report($this->locationIds($locationId), $from, $to),
            'locations' => $this->locations($request), 'locationId' => $locationId,
            'from' => $from, 'to' => $to,
        ]);
    }

    private function locationId(Request $request, ?int $requested): ?int
    {
        if ($request->user()->isAdmin() || $request->user()->can('financial.global.view')) {
            if ($requested) {
                abort_unless(Location::query()->active()->whereKey($requested)->exists(), 403);
            }

            return $requested;
        }
        $own = $request->user()->primaryLocation()?->id;
        abort_unless($own && (! $requested || $requested === $own), 403);

        return $own;
    }

    private function assertLocation(Request $request, int $locationId): void
    {
        $this->locationId($request, $locationId);
    }

    private function locationIds(?int $locationId): array
    {
        return $locationId ? [$locationId] : Location::query()->active()->pluck('id')->all();
    }

    private function locations(Request $request)
    {
        return Location::query()->active()
            ->whereIn('id', $this->locationIds($this->locationId($request, null)))
            ->orderBy('name')->get();
    }
}
