<?php

namespace App\Services\Finance;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingVoucher;
use App\Models\Currency;
use App\Models\DailyCashReconciliation;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingBookService
{
    public function __construct(private readonly FinancialPeriodResolver $periods) {}

    public function createVoucher(array $data, ?UploadedFile $proof, User $actor): AccountingVoucher
    {
        $path = null;
        try {
            return DB::transaction(function () use ($data, $proof, $actor, &$path): AccountingVoucher {
                $existing = AccountingVoucher::query()->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    abort_unless($existing->location_id === (int) $data['location_id'], 409);

                    return $existing;
                }

                Location::query()->active()->findOrFail($data['location_id']);
                $currency = $this->baseCurrency();
                $method = PaymentMethod::query()->active()->findOrFail($data['payment_method_id']);
                $treasury = $this->activeAccount($data['treasury_account_id']);
                $counter = $this->activeAccount($data['counter_account_id']);
                $subtype = $method->type === 'cash' ? 'cash' : 'bank';
                if ($treasury->type !== 'asset' || $treasury->subtype !== $subtype
                    || $counter->id === $treasury->id || in_array($counter->subtype, ['cash', 'bank'], true)) {
                    throw ValidationException::withMessages(['treasury_account_id' => 'اختر حساب خزينة مناسبًا للطريقة وحسابًا مقابلًا غير نقدي.']);
                }
                if ($method->requires_reference && blank($data['external_reference'])) {
                    throw ValidationException::withMessages(['external_reference' => 'طريقة الدفع تحتاج رقم مرجع.']);
                }
                if ($method->requires_verification && ! $proof) {
                    throw ValidationException::withMessages(['payment_proof' => 'أرفق إثبات التحويل لهذه الطريقة.']);
                }
                if (AccountingVoucher::query()->where('location_id', $data['location_id'])
                    ->where('type', $data['type'])->where('external_reference', $data['external_reference'])->exists()) {
                    throw ValidationException::withMessages(['external_reference' => 'هذا المرجع مسجل مسبقًا في الفرع لنوع السند نفسه.']);
                }
                if ($proof) {
                    $path = $proof->store('accounting/voucher-proofs', 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['payment_proof' => 'تعذّر حفظ إثبات الدفع.']);
                    }
                }
                $voucher = AccountingVoucher::query()->create([
                    'request_key' => $data['request_key'],
                    'number' => $this->number($data['type'] === 'receipt' ? 'RV' : 'PV'),
                    'type' => $data['type'], 'status' => 'draft',
                    'location_id' => $data['location_id'], 'payment_method_id' => $method->id,
                    'treasury_account_id' => $treasury->id, 'counter_account_id' => $counter->id,
                    'currency_id' => $currency->id, 'voucher_date' => $data['voucher_date'],
                    'amount' => $this->cents($data['amount']) / 100,
                    'party_name' => trim($data['party_name']),
                    'external_reference' => trim($data['external_reference']),
                    'description' => trim($data['description']),
                    'payment_proof' => $path, 'created_by' => $actor->id,
                ]);
                ActivityLogger::log($actor->id, 'accounting.voucher_created', 'accounting', 'accounting_vouchers',
                    $voucher->id, null, ['type' => $voucher->type, 'amount' => $voucher->amount]);

                return $voucher;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function postVoucher(AccountingVoucher $voucher, User $actor): AccountingVoucher
    {
        return DB::transaction(function () use ($voucher, $actor): AccountingVoucher {
            $locked = AccountingVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['voucher' => 'يمكن ترحيل السند المسودة مرة واحدة فقط.']);
            }
            $method = PaymentMethod::query()->active()->findOrFail($locked->payment_method_id);
            $treasury = $this->activeAccount($locked->treasury_account_id);
            $counter = $this->activeAccount($locked->counter_account_id);
            if ($treasury->subtype !== ($method->type === 'cash' ? 'cash' : 'bank')
                || in_array($counter->subtype, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['voucher' => 'تغيّرت حسابات السند أو طريقة الدفع؛ لا يمكن ترحيله.']);
            }
            if ($locked->currency_id !== $this->baseCurrency()->id) {
                throw ValidationException::withMessages(['voucher' => 'العملة الأساسية تغيرت؛ سجّل سندًا جديدًا بالعملة الحالية.']);
            }
            if ($method->requires_verification && (! $locked->payment_proof
                || ! Storage::disk('local')->exists($locked->payment_proof))) {
                throw ValidationException::withMessages(['voucher' => 'إثبات الدفع مفقود.']);
            }
            if ($treasury->subtype === 'cash') {
                if ($locked->voucher_date->toDateString() !== now()->toDateString()) {
                    throw ValidationException::withMessages(['voucher' => 'السند النقدي يجب أن يُرحّل بتاريخ اليوم فقط.']);
                }
                $this->assertCashOpen($locked->location_id);
            }

            $cents = $this->cents($locked->amount);
            $isReceipt = $locked->type === 'receipt';
            $journal = $this->postJournal(
                $locked->location_id, $locked->voucher_date->toDateString(), $locked->type,
                "سند ".($isReceipt ? 'قبض' : 'صرف')." {$locked->number}: {$locked->description}",
                [
                    ['account_id' => $treasury->id, 'debit' => $isReceipt ? $cents / 100 : 0, 'credit' => $isReceipt ? 0 : $cents / 100],
                    ['account_id' => $counter->id, 'debit' => $isReceipt ? 0 : $cents / 100, 'credit' => $isReceipt ? $cents / 100 : 0],
                ], $actor, 'accounting_vouchers', $locked->id,
            );
            $locked->update([
                'status' => 'posted', 'posted_by' => $actor->id,
                'posted_at' => now(), 'accounting_journal_id' => $journal->id,
            ]);
            ActivityLogger::log($actor->id, 'accounting.voucher_posted', 'accounting', 'accounting_vouchers',
                $locked->id, ['status' => 'draft'], ['status' => 'posted', 'journal_id' => $journal->id]);

            return $locked->fresh();
        });
    }

    public function cancelVoucher(AccountingVoucher $voucher, User $actor): AccountingVoucher
    {
        return DB::transaction(function () use ($voucher, $actor): AccountingVoucher {
            $locked = AccountingVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['voucher' => 'الإلغاء المباشر للمسودة فقط؛ القيد المرحّل يحتاج عكسًا.']);
            }
            $locked->update(['status' => 'cancelled']);
            ActivityLogger::log($actor->id, 'accounting.voucher_cancelled', 'accounting', 'accounting_vouchers',
                $locked->id, ['status' => 'draft'], ['status' => 'cancelled']);

            return $locked;
        });
    }

    public function createManualJournal(array $data, User $actor): AccountingJournal
    {
        return DB::transaction(function () use ($data, $actor): AccountingJournal {
            $existing = AccountingJournal::query()->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->location_id === (int) $data['location_id'], 409);

                return $existing;
            }
            Location::query()->active()->findOrFail($data['location_id']);
            foreach ($data['lines'] as $line) {
                $account = $this->activeAccount($line['account_id']);
                if (in_array($account->subtype, ['cash', 'bank'], true)) {
                    throw ValidationException::withMessages(['lines' => 'استخدم سند قبض أو صرف لأي حركة تغير رصيد الصندوق أو البنك.']);
                }
            }

            return $this->postJournal($data['location_id'], $data['entry_date'], 'manual',
                trim($data['description']), $data['lines'], $actor, requestKey: $data['request_key']);
        });
    }

    public function reverseVoucher(AccountingVoucher $voucher, string $reason, User $actor): AccountingVoucher
    {
        return DB::transaction(function () use ($voucher, $reason, $actor): AccountingVoucher {
            $locked = AccountingVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            if ($locked->status !== 'posted' || ! $locked->accounting_journal_id) {
                throw ValidationException::withMessages(['voucher' => 'لا يمكن عكس سند غير مرحّل أو معكوس مسبقًا.']);
            }
            if ($locked->treasuryAccount->subtype === 'cash') {
                if ($locked->posted_at?->toDateString() !== now()->toDateString()) {
                    throw ValidationException::withMessages(['voucher' => 'عكس السند النقدي مسموح في يوم ترحيله قبل إقفال الصندوق فقط.']);
                }
                $this->assertCashOpen($locked->location_id);
            }
            $original = AccountingJournal::query()->lockForUpdate()->findOrFail($locked->accounting_journal_id);
            if ($original->reversal()->exists()) {
                throw ValidationException::withMessages(['voucher' => 'تم عكس قيد السند مسبقًا.']);
            }
            $reversal = $this->makeReversal($original, $reason, $actor);
            $locked->update([
                'status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $actor->id,
                'reversal_reason' => $reason, 'reversal_journal_id' => $reversal->id,
            ]);
            ActivityLogger::log($actor->id, 'accounting.voucher_reversed', 'accounting', 'accounting_vouchers',
                $locked->id, ['status' => 'posted'], ['status' => 'reversed', 'reason' => $reason]);

            return $locked->fresh();
        });
    }

    public function reverseJournal(AccountingJournal $journal, string $reason, User $actor): AccountingJournal
    {
        return DB::transaction(function () use ($journal, $reason, $actor): AccountingJournal {
            $locked = AccountingJournal::query()->lockForUpdate()->findOrFail($journal->id);
            if ($locked->kind === 'reversal' || $locked->reversal()->exists()) {
                throw ValidationException::withMessages(['journal' => 'تم عكس هذا القيد مسبقًا.']);
            }
            if ($locked->kind !== 'manual') {
                throw ValidationException::withMessages(['journal' => 'اعكس سند القبض أو الصرف من صفحة السند.']);
            }

            return $this->makeReversal($locked, $reason, $actor);
        });
    }

    private function makeReversal(AccountingJournal $original, string $reason, User $actor): AccountingJournal
    {
        $lines = $original->lines()->get()->map(fn ($line) => [
            'account_id' => $line->accounting_account_id,
            'debit' => $line->credit, 'credit' => $line->debit,
            'memo' => mb_substr('عكس: '.($line->memo ?? ''), 0, 255),
        ])->all();
        $reversal = $this->postJournal($original->location_id, now()->toDateString(), 'reversal',
            "عكس القيد {$original->number}: {$reason}", $lines, $actor,
            'accounting_journal_reversals', $original->id, $original->id);
        ActivityLogger::log($actor->id, 'accounting.journal_reversed', 'accounting', 'accounting_journals',
            $original->id, null, ['reversal_id' => $reversal->id, 'reason' => $reason]);

        return $reversal;
    }

    private function postJournal(
        int $locationId, string $date, string $kind, string $description, array $lines,
        User $actor, ?string $sourceType = null, ?int $sourceId = null,
        ?int $reversesId = null, ?string $requestKey = null,
    ): AccountingJournal {
        if (count($lines) < 2 || count($lines) > 30) {
            throw ValidationException::withMessages(['lines' => 'القيد يحتاج سطرين إلى 30 سطرًا.']);
        }
        $normalized = [];
        $debits = $credits = 0;
        foreach ($lines as $line) {
            $this->activeAccount($line['account_id']);
            $debit = $this->cents($line['debit'] ?? 0);
            $credit = $this->cents($line['credit'] ?? 0);
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(['lines' => 'يجب أن يكون لكل سطر مبلغ مدين أو دائن موجب واحد فقط.']);
            }
            $normalized[] = [
                'accounting_account_id' => $line['account_id'],
                'debit' => $debit / 100, 'credit' => $credit / 100,
                'memo' => $line['memo'] ?? null,
            ];
            $debits += $debit;
            $credits += $credit;
        }
        if ($debits <= 0 || $debits !== $credits || $debits > 99999999999999) {
            throw ValidationException::withMessages(['lines' => 'مجموع المدين والدائن يجب أن يتساويا وبمبلغ موجب.']);
        }
        $period = $this->periods->openForDate($date, true);
        $currency = $this->baseCurrency();
        $journal = AccountingJournal::query()->create([
            'number' => $this->number('JV'), 'request_key' => $requestKey,
            'location_id' => $locationId, 'financial_period_id' => $period->id,
            'currency_code' => $currency->code, 'entry_date' => $date,
            'kind' => $kind, 'description' => mb_substr($description, 0, 500),
            'total_debit' => $debits / 100, 'total_credit' => $credits / 100,
            'source_type' => $sourceType, 'source_id' => $sourceId,
            'reverses_journal_id' => $reversesId,
            'created_by' => $actor->id, 'posted_at' => now(),
        ]);
        $journal->lines()->createMany($normalized);
        ActivityLogger::log($actor->id, 'accounting.journal_posted', 'accounting', 'accounting_journals',
            $journal->id, null, ['kind' => $kind, 'total' => $debits / 100]);

        return $journal;
    }

    private function activeAccount(int $id): AccountingAccount
    {
        $account = AccountingAccount::query()->where('is_active', true)->find($id);
        if (! $account) {
            throw ValidationException::withMessages(['account_id' => 'الحساب المختار غير متاح.']);
        }

        return $account;
    }

    private function baseCurrency(): Currency
    {
        $currency = Currency::query()->where('is_base', true)->where('is_active', true)->first();
        if (! $currency) {
            throw ValidationException::withMessages(['currency' => 'اضبط العملة الأساسية أولًا.']);
        }

        return $currency;
    }

    private function cents(string|float|int $amount): int
    {
        $text = (string) $amount;
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages(['amount' => 'مبلغ غير صالح.']);
        }
        [$units, $decimal] = array_pad(explode('.', $text, 2), 2, '');
        $units = ltrim($units, '0') ?: '0';
        if (strlen($units) > 12) {
            throw ValidationException::withMessages(['amount' => 'المبلغ يتجاوز الحد المسموح.']);
        }

        return ((int) $units * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
    }

    private function assertCashOpen(int $locationId): void
    {
        if (DailyCashReconciliation::query()->where('location_id', $locationId)
            ->whereDate('business_date', '>=', now()->toDateString())->exists()) {
            throw ValidationException::withMessages(['voucher' => 'الصندوق مقفل؛ لا يمكن تغيير النقد اليوم.']);
        }
    }
}
