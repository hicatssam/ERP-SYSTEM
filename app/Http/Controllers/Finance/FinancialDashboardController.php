<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\Auth;

class FinancialDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $monthStart = now()->startOfMonth();

        $locationIds = $user->isAdmin()
            ? Location::pluck('id')
            : collect([$user->primaryLocation()?->id])->filter();

        $data = $this->buildFinancialData($locationIds, $monthStart);
        $locations = $user->isAdmin() ? Location::active()->get() : collect();

        return view('finance.dashboard', compact('data', 'locations'));
    }

    public function branch(Location $location)
    {
        $user = Auth::user();
        if (! $user->isAdmin() && $user->primaryLocation()?->id !== $location->id) {
            abort(403);
        }

        $monthStart = now()->startOfMonth();
        $data = $this->buildFinancialData(collect([$location->id]), $monthStart);

        return view('finance.dashboard', compact('data', 'location'));
    }

    private function buildFinancialData($locationIds, $monthStart): array
    {
        $grossSales = Invoice::whereIn('location_id', $locationIds)->where('status', 'active')
            ->whereBetween('issued_at', [$monthStart, now()])->sum('subtotal');

        $discounts = Invoice::whereIn('location_id', $locationIds)->where('status', 'active')
            ->whereBetween('issued_at', [$monthStart, now()])->sum('discount_amount');

        $netSales = Invoice::whereIn('location_id', $locationIds)->where('status', 'active')
            ->whereBetween('issued_at', [$monthStart, now()])->sum('total_amount');

        $collections = Payment::whereIn('location_id', $locationIds)->where('status', 'confirmed')
            ->whereBetween('paid_at', [$monthStart, now()])->sum('amount');

        $pendingVerification = Payment::whereIn('location_id', $locationIds)->where('status', 'pending_verification')
            ->whereBetween('paid_at', [$monthStart, now()])->sum('amount');

     $refunds = Refund::query()
    ->whereIn('payment_id', function ($query) use ($locationIds) {
        $query->select('id')
            ->from('payments')
            ->whereIn('location_id', $locationIds);
    })
    ->whereBetween('processed_at', [$monthStart, now()])
    ->sum('amount');
        $outstanding = Invoice::whereIn('location_id', $locationIds)->where('status', 'active')->sum('remaining_amount');

        $invoiceCount = Invoice::whereIn('location_id', $locationIds)->where('status', 'active')
            ->whereBetween('issued_at', [$monthStart, now()])->count();

        $cancelledCount = Invoice::whereIn('location_id', $locationIds)->where('status', 'cancelled')
            ->whereBetween('issued_at', [$monthStart, now()])->count();

        return compact(
            'grossSales', 'discounts', 'netSales', 'collections',
            'pendingVerification', 'refunds', 'outstanding',
            'invoiceCount', 'cancelledCount'
        );
    }
}
