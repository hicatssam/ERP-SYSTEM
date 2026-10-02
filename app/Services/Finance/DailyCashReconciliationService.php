<?php

namespace App\Services\Finance;

use App\Models\BranchCashMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Physical cash in the branch's base currency, never invoice value or bank transfers. */
class DailyCashReconciliationService
{
    public const COMPONENTS = [
        'sales', 'customer_receipts', 'employee_receipts', 'other_income', 'expenses',
        'supplier_payments', 'refunds', 'transfer_in', 'transfer_out',
    ];

    public function forDay(int $locationId, string $businessDate): array
    {
        $start = CarbonImmutable::parse($businessDate)->startOfDay();
        $end = $start->addDay();

        $cashSales = DB::table('payments as p')
            ->join('payment_methods as pm', 'pm.id', '=', 'p.payment_method_id')
            ->where('p.location_id', $locationId)
            ->where('pm.type', 'cash')
            ->whereIn('p.status', ['confirmed', 'corrected', 'refunded'])
            ->where('p.paid_at', '>=', $start)->where('p.paid_at', '<', $end)
            ->sum('p.amount');

        $customerReceipts = DB::table('customer_payments as cp')
            ->join('payment_methods as pm', 'pm.id', '=', 'cp.payment_method_id')
            ->where('cp.location_id', $locationId)->where('cp.status', 'confirmed')
            ->where('pm.type', 'cash')
            ->where('cp.paid_at', '>=', $start)->where('cp.paid_at', '<', $end)
            ->sum('cp.amount');

        $employeeReceipts = DB::table('employee_purchase_receipts as er')
            ->join('payment_methods as pm', 'pm.id', '=', 'er.payment_method_id')
            ->where('er.location_id', $locationId)->where('er.status', 'posted')
            ->where('pm.type', 'cash')
            ->where('er.posted_at', '>=', $start)->where('er.posted_at', '<', $end)
            ->sum('er.amount');

        $refunds = DB::table('refunds as r')
            ->join('payments as p', 'p.id', '=', 'r.payment_id')
            ->join('payment_methods as pm', 'pm.id', '=', 'r.payment_method_id')
            ->where('p.location_id', $locationId)->where('pm.type', 'cash')
            ->where('r.processed_at', '>=', $start)->where('r.processed_at', '<', $end)
            ->sum('r.amount');

        // Expense drafts and merely approved expenses have not been posted.
        // A void reverses the accounting entry, not necessarily the physical payout.
        $expenses = DB::table('expenses as e')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'e.payment_method_id')
            ->where('e.location_id', $locationId)->whereNull('e.deleted_at')
            ->whereIn('e.status', ['posted', 'void'])
            // Eloquent date casts can persist midnight timestamps on SQLite.
            // An indexed range accepts both a DATE and its timestamp representation.
            ->where('e.expense_date', '>=', $businessDate)
            ->where('e.expense_date', '<', $end->toDateString());
        $unclassifiedExpenses = (clone $expenses)->whereNull('e.payment_method_id')->count();
        $cashExpenses = (clone $expenses)->where('pm.type', 'cash')->sum('e.amount');

        $payroll = DB::table('payroll_payments as pp')
            ->join('payment_methods as pm', 'pm.id', '=', 'pp.payment_method_id')
            ->leftJoin('currencies as c', 'c.id', '=', 'pp.currency_id')
            ->where('pp.location_id', $locationId)->where('pm.type', 'cash')
            ->whereIn('pp.status', ['posted', 'void'])
            ->where('pp.paid_at', '>=', $start)->where('pp.paid_at', '<', $end);
        $unclassifiedPayroll = (clone $payroll)->whereNull('pp.currency_id')->count();
        $foreignCashPayroll = (clone $payroll)->where('c.is_base', false)->count();
        $cashPayroll = (clone $payroll)->where('c.is_base', true)->sum('pp.amount');

        $supplierPayments = DB::table('supplier_payments as sp')
            ->join('payment_methods as pm', 'pm.id', '=', 'sp.payment_method_id')
            ->join('currencies as c', 'c.id', '=', 'sp.currency_id')
            ->where('sp.location_id', $locationId)->where('sp.status', 'confirmed')
            ->where('sp.payment_date', '>=', $businessDate)
            ->where('sp.payment_date', '<', $end->toDateString())
            ->where('pm.type', 'cash');
        // Foreign notes cannot be added to an ILS/base-currency physical count.
        $foreignCashPayments = (clone $supplierPayments)->where('c.is_base', false)->count();
        $cashSupplierPayments = (clone $supplierPayments)->where('c.is_base', true)->sum('sp.amount');

        $manual = BranchCashMovement::query()
            ->where('location_id', $locationId)->whereNull('voided_at')
            ->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');

        return [
            'components' => [
                'sales' => round((float) $cashSales, 2),
                'customer_receipts' => round((float) $customerReceipts, 2),
                'employee_receipts' => round((float) $employeeReceipts, 2),
                'other_income' => round((float) ($manual['other_income'] ?? 0), 2),
                'expenses' => round((float) $cashExpenses + (float) $cashPayroll, 2),
                'supplier_payments' => round((float) $cashSupplierPayments, 2),
                'refunds' => round((float) $refunds, 2),
                'transfer_in' => round((float) ($manual['transfer_in'] ?? 0), 2),
                'transfer_out' => round((float) ($manual['transfer_out'] ?? 0), 2),
            ],
            'unclassified_expenses' => $unclassifiedExpenses,
            'foreign_cash_supplier_payments' => $foreignCashPayments,
            'unclassified_cash_payroll' => $unclassifiedPayroll,
            'foreign_cash_payroll' => $foreignCashPayroll,
        ];
    }

    public function expected(string|float $opening, array $components): float
    {
        $cents = (int) round((float) $opening * 100);
        foreach (['sales', 'customer_receipts', 'employee_receipts', 'other_income', 'transfer_in'] as $key) {
            $cents += (int) round(($components[$key] ?? 0) * 100);
        }
        foreach (['expenses', 'supplier_payments', 'refunds', 'transfer_out'] as $key) {
            $cents -= (int) round(($components[$key] ?? 0) * 100);
        }

        return $cents / 100;
    }
}
