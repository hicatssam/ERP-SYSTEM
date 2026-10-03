<?php

namespace App\Services\Finance;

use App\Models\AccountingAccount;
use App\Models\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reports cover posted double-entry journals only. Legacy operational records are not silently mixed in. */
class AccountingReportService
{
    public function report(array $locationIds, string $from, string $to): array
    {
        $currency = Currency::query()->where('is_base', true)->where('is_active', true)->first();
        if (! $currency) {
            throw ValidationException::withMessages(['currency' => 'اضبط العملة الأساسية قبل عرض التقرير.']);
        }
        $endExclusive = CarbonImmutable::parse($to)->addDay()->toDateString();
        $foreign = DB::table('accounting_journals')->whereIn('location_id', $locationIds)
            ->where('entry_date', '<', $endExclusive)->where('currency_code', '!=', $currency->code)->exists();
        if ($foreign) {
            throw ValidationException::withMessages(['currency' => 'توجد قيود بعملة أساسية سابقة؛ يلزم إعادة تقييمها قبل دمج التقرير.']);
        }

        $opening = $this->totals($locationIds, null, $from, true);
        $activity = $this->totals($locationIds, $from, $to, false);
        $rows = [];
        $groups = ['asset' => 0, 'liability' => 0, 'equity' => 0, 'revenue' => 0, 'expense' => 0];
        $trial = ['opening_debit' => 0, 'opening_credit' => 0, 'period_debit' => 0,
            'period_credit' => 0, 'closing_debit' => 0, 'closing_credit' => 0];
        foreach (AccountingAccount::query()->orderBy('code')->get() as $account) {
            $id = $account->id;
            $openingDebit = $this->cents($opening[$id]->debit_total ?? 0);
            $openingCredit = $this->cents($opening[$id]->credit_total ?? 0);
            $periodDebit = $this->cents($activity[$id]->debit_total ?? 0);
            $periodCredit = $this->cents($activity[$id]->credit_total ?? 0);
            $openingNet = $openingDebit - $openingCredit;
            $closingNet = $openingNet + $periodDebit - $periodCredit;
            if (! $openingNet && ! $periodDebit && ! $periodCredit) {
                continue;
            }
            $row = [
                'account' => $account,
                'opening_debit' => max(0, $openingNet), 'opening_credit' => max(0, -$openingNet),
                'period_debit' => $periodDebit, 'period_credit' => $periodCredit,
                'closing_debit' => max(0, $closingNet), 'closing_credit' => max(0, -$closingNet),
            ];
            foreach ($trial as $key => $_) {
                $trial[$key] += $row[$key];
            }
            $rows[] = $row;

            // Statement of financial position is cumulative; income statement is for the date range.
            $groups[$account->type] += match ($account->type) {
                'asset', 'expense' => $closingNet,
                default => -$closingNet,
            };
        }

        $periodRevenue = $periodExpense = 0;
        foreach ($rows as $row) {
            $account = $row['account'];
            if ($account->type === 'revenue') {
                $periodRevenue += $row['period_credit'] - $row['period_debit'];
            } elseif ($account->type === 'expense') {
                $periodExpense += $row['period_debit'] - $row['period_credit'];
            }
        }
        $cumulativeEarnings = $groups['revenue'] - $groups['expense'];
        $liabilitiesEquity = $groups['liability'] + $groups['equity'] + $cumulativeEarnings;

        $unlinkedOperations = DB::table('sales_ledger_entries as legacy')
            ->leftJoin('accounting_journals as posted', function ($join): void {
                $join->on('posted.source_id', '=', 'legacy.id')
                    ->where('posted.source_type', '=', 'sales_ledger_entries');
            })
            ->whereIn('legacy.location_id', $locationIds)
            ->where('legacy.entry_date', '<', $endExclusive)
            ->whereIn('legacy.entry_type', [
                'sale', 'sale_cancellation', 'payment_collection', 'payment_reversal',
                'refund', 'expense', 'expense_reversal', 'employee_purchase', 'employee_collection',
            ])->whereNull('posted.id')->count();

        return [
            'currency' => $currency,
            'unlinked_operations' => $unlinkedOperations,
            'rows' => $rows, 'trial' => $trial,
            'income' => ['revenue' => $periodRevenue, 'expenses' => $periodExpense,
                'net' => $periodRevenue - $periodExpense],
            'position' => [
                'assets' => $groups['asset'], 'liabilities' => $groups['liability'],
                'equity' => $groups['equity'], 'cumulative_earnings' => $cumulativeEarnings,
                'liabilities_and_equity' => $liabilitiesEquity,
                'difference' => $groups['asset'] - $liabilitiesEquity,
            ],
        ];
    }

    private function totals(array $locations, ?string $from, string $boundary, bool $before): array
    {
        $query = DB::table('accounting_journal_lines as line')
            ->join('accounting_journals as journal', 'journal.id', '=', 'line.accounting_journal_id')
            ->whereIn('journal.location_id', $locations);
        if ($before) {
            $query->where('journal.entry_date', '<', $boundary);
        } else {
            $query->where('journal.entry_date', '>=', $from)
                ->where('journal.entry_date', '<', CarbonImmutable::parse($boundary)->addDay()->toDateString());
        }

        return $query->select('line.accounting_account_id')
            ->selectRaw('SUM(line.debit) AS debit_total, SUM(line.credit) AS credit_total')
            ->groupBy('line.accounting_account_id')->get()->keyBy('accounting_account_id')->all();
    }

    private function cents(string|float|int $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
