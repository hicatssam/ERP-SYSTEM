<?php

namespace App\Services\Finance;

use App\Enums\ExpenseCategoryType;
use App\Enums\LedgerEntryType;
use App\Enums\OrderType;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\OrderItem;
use App\Models\SalesLedgerEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitabilityService
{
    public function summary(Collection|array $locationIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $ids = $this->normalizeLocationIds($locationIds);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        if ($ids === []) {
            return $this->emptySummary();
        }

        /*
        |--------------------------------------------------------------------------
        | Sales population
        |--------------------------------------------------------------------------
        |
        | Total sales can include Special Cake invoices.  Current COGS snapshots
        | are on normal order_items only, therefore we MUST NOT present those
        | unsupported invoice types as if they had known COGS.
        |
        */
        $totalNetSales = $this->invoiceNetSales($ids, $fromDate, $toDate);
        $costableNetSales = $this->invoiceNetSales(
            $ids,
            $fromDate,
            $toDate,
            OrderType::Order->value
        );
        $excludedSales = max(0, round($totalNetSales - $costableNetSales, 2));

        $costQuery = $this->costedItemsQuery($ids, $fromDate, $toDate);

        $costedNetSales = (float) (clone $costQuery)
            ->whereNotNull('order_items.cost_snapshotted_at')
            ->sum(DB::raw('COALESCE(order_items.net_revenue_snapshot, order_items.line_total)'));

        $cogs = (float) (clone $costQuery)
            ->whereNotNull('order_items.cost_snapshotted_at')
            ->sum('order_items.cost_total_snapshot');

        $missingCostItems = (int) (clone $costQuery)
            ->whereNull('order_items.cost_snapshotted_at')
            ->count('order_items.id');

        $missingCostRevenue = (float) (clone $costQuery)
            ->whereNull('order_items.cost_snapshotted_at')
            ->sum(DB::raw('COALESCE(order_items.net_revenue_snapshot, order_items.line_total)'));

        $expenses = $this->netExpenses($ids, $fromDate, $toDate);
        $knownGrossProfit = $costedNetSales - $cogs;

        /*
         * This number is intentionally called partial/estimated whenever any
         * sales line lacks cost or an invoice type has no COGS implementation.
         * It remains useful operationally, but is never presented as final P&L.
         */
        $operatingProfit = $totalNetSales - $cogs - $expenses;

        $coverage = $costableNetSales > 0
            ? min(100, max(0, ($costedNetSales / $costableNetSales) * 100))
            : 100.0;

        $costComplete = $missingCostItems === 0;
        $scopeComplete = $excludedSales <= 0.009;
        $reliable = $costComplete && $scopeComplete;

        return [
            'net_sales' => $totalNetSales,
            'costable_net_sales' => $costableNetSales,
            'excluded_sales_revenue' => $excludedSales,
            'costed_net_sales' => $costedNetSales,
            'cogs' => $cogs,
            'gross_profit_costed' => $knownGrossProfit,
            'gross_margin_costed' => $costedNetSales > 0 ? ($knownGrossProfit / $costedNetSales) * 100 : 0,
            'operating_expenses' => $expenses,
            'operating_profit' => $operatingProfit,
            'operating_margin' => $totalNetSales > 0 ? ($operatingProfit / $totalNetSales) * 100 : 0,
            'missing_cost_items' => $missingCostItems,
            'missing_cost_revenue' => $missingCostRevenue,
            'cost_coverage_percent' => $coverage,
            'is_cost_complete' => $costComplete,
            'is_scope_complete' => $scopeComplete,
            'is_profit_reliable' => $reliable,
        ];
    }

    public function products(Collection|array $locationIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ids = $this->normalizeLocationIds($locationIds);

        if ($ids === []) {
            return collect();
        }

        return $this->costedItemsQuery($ids, $from->toDateString(), $to->toDateString())
            ->select([
                'order_items.product_id',
                'order_items.product_name',
            ])
            ->selectRaw('SUM(order_items.quantity) AS qty')
            ->selectRaw('SUM(COALESCE(order_items.net_revenue_snapshot, order_items.line_total)) AS revenue')
            ->selectRaw('SUM(CASE WHEN order_items.cost_snapshotted_at IS NOT NULL THEN COALESCE(order_items.net_revenue_snapshot, order_items.line_total) ELSE 0 END) AS costed_revenue')
            ->selectRaw('SUM(CASE WHEN order_items.cost_snapshotted_at IS NOT NULL THEN COALESCE(order_items.cost_total_snapshot, 0) ELSE 0 END) AS cogs')
            ->selectRaw('SUM(CASE WHEN order_items.cost_snapshotted_at IS NULL THEN COALESCE(order_items.net_revenue_snapshot, order_items.line_total) ELSE 0 END) AS missing_cost_revenue')
            ->selectRaw('SUM(CASE WHEN order_items.cost_snapshotted_at IS NULL THEN 1 ELSE 0 END) AS missing_cost_items')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('revenue')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->revenue;
                $costedRevenue = (float) $row->costed_revenue;
                $cogs = (float) $row->cogs;

                // Profit and margin are calculated ONLY for lines with known cost.
                // Unknown-cost revenue is never treated as zero-cost profit.
                $row->profit = $costedRevenue - $cogs;
                $row->margin = $costedRevenue > 0 ? ($row->profit / $costedRevenue) * 100 : 0;
                $row->coverage_percent = $revenue > 0
                    ? min(100, max(0, ($costedRevenue / $revenue) * 100))
                    : 100;

                return $row;
            });
    }

    public function expensesByCategory(Collection|array $locationIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ids = $this->normalizeLocationIds($locationIds);

        if ($ids === []) {
            return collect();
        }

        return SalesLedgerEntry::query()
            ->join('expenses', function ($join) {
                $join->on('expenses.id', '=', 'sales_ledger_entries.reference_id')
                    ->where('sales_ledger_entries.reference_type', '=', 'expenses');
            })
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereIn('sales_ledger_entries.location_id', $ids)
            ->whereDate('sales_ledger_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('sales_ledger_entries.entry_date', '<=', $to->toDateString())
            ->whereIn('sales_ledger_entries.entry_type', [
                LedgerEntryType::Expense->value,
                LedgerEntryType::ExpenseReversal->value,
            ])
            ->select('expense_categories.id', 'expense_categories.name', 'expense_categories.classification')
            ->selectRaw(
                'SUM(CASE WHEN sales_ledger_entries.entry_type = ? THEN sales_ledger_entries.amount ELSE -sales_ledger_entries.amount END) AS amount',
                [LedgerEntryType::Expense->value]
            )
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.classification')
            ->orderByDesc('amount')
            ->get()
            ->map(function ($row) {
                $row->classification_label = ExpenseCategoryType::tryFrom((string) $row->classification)?->label() ?? (string) $row->classification;
                return $row;
            });
    }

    public function locations(Collection|array $locationIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $ids = $this->normalizeLocationIds($locationIds);

        if ($ids === []) {
            return collect();
        }

        $locations = Location::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);

        return $locations->map(function (Location $location) use ($from, $to) {
            $summary = $this->summary([$location->id], $from, $to);

            return (object) array_merge([
                'location_id' => $location->id,
                'location_name' => $location->name,
            ], $summary);
        });
    }

    private function invoiceNetSales(array $ids, string $from, string $to, ?string $orderType = null): float
    {
        return (float) Invoice::query()
            ->whereIn('location_id', $ids)
            ->where('status', 'active')
            ->whereDate('issued_at', '>=', $from)
            ->whereDate('issued_at', '<=', $to)
            ->when($orderType !== null, fn ($query) => $query->where('order_type', $orderType))
            ->selectRaw('COALESCE(SUM(subtotal - discount_amount), 0) AS value')
            ->value('value');
    }

    private function costedItemsQuery(array $ids, string $from, string $to)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.location_id', $ids)
            ->whereIn('orders.status', ['confirmed', 'completed'])
            ->whereDate(DB::raw('COALESCE(orders.confirmed_at, orders.created_at)'), '>=', $from)
            ->whereDate(DB::raw('COALESCE(orders.confirmed_at, orders.created_at)'), '<=', $to)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.order_id', 'orders.id')
                    ->where('invoices.order_type', OrderType::Order->value)
                    ->where('invoices.status', 'active')
                    ->whereNull('invoices.deleted_at');
            });
    }

    private function netExpenses(array $ids, string $from, string $to): float
    {
        $posted = (float) SalesLedgerEntry::query()
            ->whereIn('location_id', $ids)
            ->where('entry_type', LedgerEntryType::Expense->value)
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->sum('amount');

        $reversed = (float) SalesLedgerEntry::query()
            ->whereIn('location_id', $ids)
            ->where('entry_type', LedgerEntryType::ExpenseReversal->value)
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->sum('amount');

        return $posted - $reversed;
    }

    private function normalizeLocationIds(Collection|array $locationIds): array
    {
        return collect($locationIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function emptySummary(): array
    {
        return [
            'net_sales' => 0,
            'costable_net_sales' => 0,
            'excluded_sales_revenue' => 0,
            'costed_net_sales' => 0,
            'cogs' => 0,
            'gross_profit_costed' => 0,
            'gross_margin_costed' => 0,
            'operating_expenses' => 0,
            'operating_profit' => 0,
            'operating_margin' => 0,
            'missing_cost_items' => 0,
            'missing_cost_revenue' => 0,
            'cost_coverage_percent' => 100,
            'is_cost_complete' => true,
            'is_scope_complete' => true,
            'is_profit_reliable' => true,
        ];
    }
}
