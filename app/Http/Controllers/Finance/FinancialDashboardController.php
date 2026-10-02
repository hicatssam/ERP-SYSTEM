<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id']]);
        $user = $request->user();
        $canViewAll = $user->isAdmin() || $user->can('financial.global.view');
        $locationId = $request->integer('location_id') ?: null;

        if (! $canViewAll) {
            $primaryId = $user->primaryLocation()?->id;
            abort_unless($primaryId && (! $locationId || $locationId === $primaryId), 403);
            $locationId = $primaryId;
        }

        $locationIds = $locationId ? collect([$locationId]) : Location::query()->pluck('id');

        return view('finance.dashboard', [
            'data' => $this->buildFinancialData($locationIds),
            'locations' => $canViewAll ? Location::query()->orderBy('name')->get() : collect(),
            'locationId' => $locationId,
            'currencySymbol' => Currency::query()->where('is_base', true)->value('symbol') ?: '₪',
        ]);
    }

    public function branch(Request $request, Location $location): View
    {
        $user = $request->user();
        $canViewAll = $user->isAdmin() || $user->can('financial.global.view');
        abort_unless($canViewAll || $user->primaryLocation()?->id === $location->id, 403);

        return view('finance.dashboard', [
            'data' => $this->buildFinancialData(collect([$location->id])),
            'locations' => $canViewAll ? Location::query()->orderBy('name')->get() : collect(),
            'locationId' => $location->id,
            'currencySymbol' => Currency::query()->where('is_base', true)->value('symbol') ?: '₪',
        ]);
    }

    private function buildFinancialData($locationIds): array
    {
        $monthStart = now()->startOfMonth();
        $now = now();
        $invoices = Invoice::query()->whereIn('location_id', $locationIds)
            ->where('status', 'active')->whereBetween('issued_at', [$monthStart, $now]);
        $grossSales = (float) (clone $invoices)->sum('subtotal');
        $discounts = (float) (clone $invoices)->sum('discount_amount');
        $taxes = (float) (clone $invoices)->sum('tax_amount');
        $netSales = (float) (clone $invoices)->sum('total_amount');
        $invoiceCount = (clone $invoices)->count();

        // Corrected/refunded payments remain original collections; the refund is a separate flow.
        $orderCollections = (float) Payment::query()->whereIn('location_id', $locationIds)
            ->whereIn('status', ['confirmed', 'corrected', 'refunded'])
            ->whereBetween('paid_at', [$monthStart, $now])->sum('amount');
        $customerCollections = (float) DB::table('customer_payments')
            ->whereIn('location_id', $locationIds)->where('status', 'confirmed')
            ->whereBetween('paid_at', [$monthStart, $now])->sum('amount');
        $collections = round($orderCollections + $customerCollections, 2);

        $pendingOrder = (float) Payment::query()->whereIn('location_id', $locationIds)
            ->where('status', 'pending_verification')
            ->whereBetween('paid_at', [$monthStart, $now])->sum('amount');
        $pendingCustomer = (float) DB::table('customer_payments')
            ->whereIn('location_id', $locationIds)->where('status', 'pending_verification')
            ->whereBetween('paid_at', [$monthStart, $now])->sum('amount');
        $pendingVerification = round($pendingOrder + $pendingCustomer, 2);

        $refunds = (float) Refund::query()
            ->whereIn('payment_id', fn ($query) => $query->select('id')->from('payments')
                ->whereIn('location_id', $locationIds))
            ->whereBetween('processed_at', [$monthStart, $now])->sum('amount');
        $netCollections = round($collections - $refunds, 2);

        // This is the current open balance, including invoices from previous months.
        $outstanding = (float) Invoice::query()->whereIn('location_id', $locationIds)
            ->where('status', 'active')->sum('remaining_amount');
        $cancelledCount = Invoice::query()->whereIn('location_id', $locationIds)
            ->where('status', 'cancelled')->whereBetween('issued_at', [$monthStart, $now])->count();

        return compact(
            'grossSales', 'discounts', 'taxes', 'netSales', 'collections', 'netCollections',
            'pendingVerification', 'refunds', 'outstanding',
            'invoiceCount', 'cancelledCount'
        );
    }
}
