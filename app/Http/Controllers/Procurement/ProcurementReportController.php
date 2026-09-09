<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\PurchaseReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Procurement\SupplierBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProcurementReportController extends Controller
{
    use ScopesProcurementLocations;

    public function __construct(private readonly SupplierBalanceService $balances)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->can('procurement.reports.view'), 403);

        $locationIds = $this->permittedLocationIds($user);
        if ($request->filled('location_id')) {
            $locationId = $request->integer('location_id');
            abort_unless($locationIds->contains($locationId), 403);
            $locationIds = collect([$locationId]);
        }

        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        $receiptByLocation = GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->join('locations', 'locations.id', '=', 'goods_receipts.location_id')
            ->whereIn('goods_receipts.location_id', $locationIds)
            ->where('goods_receipts.status', 'posted')
            ->whereBetween('goods_receipts.received_at', [$from, $to])
            ->groupBy('locations.id', 'locations.name')
            ->select(
                'locations.name',
                DB::raw('SUM(goods_receipt_items.accepted_quantity) as accepted_quantity'),
                DB::raw('SUM(goods_receipt_items.base_line_total) as base_total'),
            )
            ->orderByDesc('base_total')
            ->get();

        $purchasesBySupplier = SupplierInvoice::query()
            ->join('suppliers', 'suppliers.id', '=', 'supplier_invoices.supplier_id')
            ->whereIn('supplier_invoices.location_id', $locationIds)
            ->where('supplier_invoices.status', '!=', 'cancelled')
            ->whereBetween('supplier_invoices.invoice_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('suppliers.id', 'suppliers.name')
            ->select(
                'suppliers.name',
                DB::raw('SUM(supplier_invoices.base_grand_total) as base_total'),
                DB::raw('SUM(supplier_invoices.remaining_amount * supplier_invoices.exchange_rate) as base_outstanding_total'),
            )
            ->orderByDesc('base_total')
            ->get();

        $returns = PurchaseReturn::query()
            ->with(['supplier', 'location', 'currency'])
            ->whereIn('location_id', $locationIds)
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->whereBetween('returned_at', [$from, $to])
            ->latest('returned_at')
            ->get();

        $aging = SupplierInvoice::query()
            ->with(['supplier', 'currency', 'location'])
            ->whereIn('location_id', $locationIds)
            ->where('status', '!=', 'cancelled')
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->get()
            ->map(function (SupplierInvoice $invoice): array {
                $daysOverdue = $invoice->due_date->isBefore(today())
                    ? $invoice->due_date->diffInDays(today())
                    : 0;

                return [
                    'invoice' => $invoice,
                    'days_overdue' => $daysOverdue,
                    'bucket' => $daysOverdue === 0
                        ? 'غير مستحق بعد'
                        : ($daysOverdue <= 30 ? '1 - 30 يوم' : ($daysOverdue <= 60 ? '31 - 60 يوم' : '+60 يوم')),
                ];
            });

        $supplierBalances = Supplier::query()
            ->with('currency')
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'supplier' => $supplier,
                'summary' => $this->balances->summary(
                    $supplier,
                    null,
                    $locationIds,
                    $user->isAdmin(),
                ),
            ])
            ->filter(fn (array $row): bool => abs($row['summary']['outstanding_balance']) > 0.004)
            ->values();

        return view('procurement.reports.index', [
            'receiptByLocation' => $receiptByLocation,
            'purchasesBySupplier' => $purchasesBySupplier,
            'returns' => $returns,
            'aging' => $aging,
            'supplierBalances' => $supplierBalances,
            'locations' => $this->permittedLocations($user),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
