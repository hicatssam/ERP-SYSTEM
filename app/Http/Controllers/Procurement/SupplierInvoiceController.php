<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Http\Requests\Procurement\StoreSupplierInvoiceRequest;
use App\Models\Currency;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Procurement\SupplierInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierInvoiceController extends Controller
{
    use ScopesProcurementLocations;

    public function __construct(
        private readonly SupplierInvoiceService $invoices
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierInvoice::class);

        $locationIds = $this->permittedLocationIds(
            $request->user()
        );

        $invoices = SupplierInvoice::query()
            ->with([
                'supplier',
                'location',
                'currency',
                'purchaseOrder',
                'goodsReceipt',
            ])
            ->whereIn('location_id', $locationIds)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->input('status')
                )
            )
            ->when(
                $request->filled('supplier_id'),
                fn ($query) => $query->where(
                    'supplier_id',
                    $request->integer('supplier_id')
                )
            )
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view(
            'procurement.supplier-invoices.index',
            [
                'invoices' => $invoices,

                'suppliers' => Supplier::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'supplier_code',
                    ]),
            ]
        );
    }

    public function create(Request $request): View
    {
        $this->authorize(
            'create',
            SupplierInvoice::class
        );

        $locationIds = $this->permittedLocationIds(
            $request->user()
        );

        return view(
            'procurement.supplier-invoices.create',
            [
                'locations' => $this->permittedLocations(
                    $request->user()
                ),

                'currencies' => Currency::query()
                    ->active()
                    ->orderBy('code')
                    ->get(),

                'suppliers' => Supplier::query()
                    ->active()
                    ->orderBy('name')
                    ->get(),

                'products' => Product::query()
                    ->active()
                    ->orderBy('name_ar')
                    ->orderBy('name')
                    ->get(),

                'purchaseOrders' => PurchaseOrder::query()
                    ->whereIn('location_id', $locationIds)
                    ->whereIn(
                        'status',
                        [
                            'approved',
                            'partially_received',
                            'received',
                        ]
                    )
                    ->with('supplier')
                    ->latest('order_date')
                    ->get(),

                'goodsReceipts' => GoodsReceipt::query()
                    ->whereIn('location_id', $locationIds)
                    ->where('status', 'posted')
                    ->with('supplier')
                    ->latest('received_at')
                    ->get(),
            ]
        );
    }

    public function store(
        StoreSupplierInvoiceRequest $request
    ): RedirectResponse {
        $this->authorize(
            'create',
            SupplierInvoice::class
        );

        $this->ensurePermittedLocation(
            $request->user(),
            $request->integer('location_id')
        );

        $invoice = $this->invoices->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route(
                'supplier-invoices.show',
                $invoice
            )
            ->with(
                'success',
                'تم تسجيل فاتورة المورد.'
            );
    }

    public function show(
        SupplierInvoice $supplierInvoice
    ): View {
        $this->authorize(
            'view',
            $supplierInvoice
        );

        $supplierInvoice->load([
            'supplier',
            'location',
            'currency',
            'creator',
            'canceller',
            'purchaseOrder',
            'goodsReceipt',
            'items.product',
            'payments.paymentMethod',
            'purchaseReturns.items.product',
        ]);

        return view(
            'procurement.supplier-invoices.show',
            [
                'supplierInvoice' => $supplierInvoice,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Print Supplier Invoice
    |--------------------------------------------------------------------------
    */

    public function printInvoice(
        SupplierInvoice $supplierInvoice
    ): View {
        $this->authorize(
            'view',
            $supplierInvoice
        );

        $supplierInvoice->load([
            'supplier',
            'location',
            'currency',
            'creator',
            'canceller',
            'purchaseOrder',
            'goodsReceipt',
            'items.product',
            'payments.paymentMethod',
            'purchaseReturns.items.product',
        ]);

        return view(
            'procurement.supplier-invoices.print',
            [
                'supplierInvoice' => $supplierInvoice,
            ]
        );
    }

    public function cancel(
        Request $request,
        SupplierInvoice $supplierInvoice
    ): RedirectResponse {
        $this->authorize(
            'cancel',
            $supplierInvoice
        );

        $supplierInvoice = $this->invoices->cancel(
            $supplierInvoice,
            $request->user()
        );

        return redirect()
            ->route(
                'supplier-invoices.show',
                $supplierInvoice
            )
            ->with(
                'success',
                'تم إلغاء فاتورة المورد.'
            );
    }
}