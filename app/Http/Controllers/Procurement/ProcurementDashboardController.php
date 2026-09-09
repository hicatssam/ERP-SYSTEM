<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProcurementDashboardController extends Controller
{
    use ScopesProcurementLocations;

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->can('dashboard.procurement'), 403);

        $locationIds = $this->permittedLocationIds($user);
        $from = now()->startOfMonth();
        $to = now();

        $openOrders = PurchaseOrder::query()
            ->whereIn('location_id', $locationIds)
            ->whereIn('status', ['draft', 'submitted', 'approved', 'partially_received'])
            ->count();
        $receiptsThisMonth = GoodsReceiptItem::query()
            ->whereHas('goodsReceipt', fn ($query) => $query
                ->whereIn('location_id', $locationIds)
                ->where('status', 'posted')
                ->whereBetween('received_at', [$from, $to]))
            ->sum('base_line_total');
        $returnsThisMonth = PurchaseReturn::query()
            ->whereIn('location_id', $locationIds)
            ->where('status', 'posted')
            ->whereBetween('returned_at', [$from, $to])
            ->sum('base_grand_total');
        // The procurement dashboard spans currencies, so convert the open
        // supplier balance to the configured base currency before summing it.
        $outstanding = SupplierInvoice::query()
            ->whereIn('location_id', $locationIds)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(remaining_amount * exchange_rate), 0) as total')
            ->value('total');
        $overdue = SupplierInvoice::query()
            ->whereIn('location_id', $locationIds)
            ->where('status', '!=', 'cancelled')
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<', today())
            ->count();
        $inventoryValue = Inventory::query()
            ->whereIn('location_id', $locationIds)
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')
            ->value('total');

        $ordersByStatus = PurchaseOrder::query()
            ->whereIn('location_id', $locationIds)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $topSuppliers = SupplierInvoice::query()
            ->join('suppliers', 'suppliers.id', '=', 'supplier_invoices.supplier_id')
            ->whereIn('supplier_invoices.location_id', $locationIds)
            ->where('supplier_invoices.status', '!=', 'cancelled')
            ->whereBetween('supplier_invoices.invoice_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('suppliers.id', 'suppliers.name')
            ->select('suppliers.name', DB::raw('SUM(supplier_invoices.base_grand_total) as total'))
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $recentReceipts = GoodsReceipt::query()
            ->with(['supplier', 'location', 'currency'])
            ->whereIn('location_id', $locationIds)
            ->latest('received_at')
            ->limit(8)
            ->get();

        return view('procurement.dashboard', [
            'stats' => compact(
                'openOrders', 'receiptsThisMonth', 'returnsThisMonth', 'outstanding',
                'overdue', 'inventoryValue',
            ),
            'ordersByStatus' => $ordersByStatus,
            'topSuppliers' => $topSuppliers,
            'recentReceipts' => $recentReceipts,
            'locations' => $this->permittedLocations($user),
        ]);
    }
}
