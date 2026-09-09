<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Http\Requests\Procurement\StorePurchaseReturnRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\SupplierInvoice;
use App\Services\Procurement\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    use ScopesProcurementLocations;

    public function __construct(private readonly PurchaseReturnService $returns)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $locationIds = $this->permittedLocationIds($request->user());

        $returns = PurchaseReturn::query()
            ->with([
                'supplier',
                'goodsReceipt',
                'supplierInvoice',
                'location',
                'currency',
                'returner.employee',
                'poster.employee',
            ])
            ->withCount('items')
            ->whereIn('location_id', $locationIds)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->latest('returned_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('procurement.purchase-returns.index', compact('returns'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseReturn::class);

        $locationIds = $this->permittedLocationIds($request->user());
        $selectedReceipt = null;

        if ($request->filled('goods_receipt_id')) {
            $selectedReceipt = GoodsReceipt::query()
                ->with(['supplier', 'currency', 'items.product', 'items.batch'])
                ->whereIn('location_id', $locationIds)
                ->where('status', 'posted')
                ->findOrFail($request->integer('goods_receipt_id'));

            $this->authorize('view', $selectedReceipt);
        }

        $receipts = GoodsReceipt::query()
            ->with(['supplier', 'currency', 'location', 'items.product', 'items.batch'])
            ->whereIn('location_id', $locationIds)
            ->where('status', 'posted')
            ->latest('received_at')
            ->get();

        $invoices = $selectedReceipt
            ? SupplierInvoice::query()
                ->where('supplier_id', $selectedReceipt->supplier_id)
                ->where('location_id', $selectedReceipt->location_id)
                ->where('currency_id', $selectedReceipt->currency_id)
                ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
                ->orderByDesc('invoice_date')
                ->get()
            : collect();

        return view('procurement.purchase-returns.create', compact(
            'receipts',
            'selectedReceipt',
            'invoices'
        ));
    }

    public function store(StorePurchaseReturnRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseReturn::class);

        $receipt = GoodsReceipt::query()->findOrFail($request->integer('goods_receipt_id'));
        $this->ensurePermittedLocation($request->user(), $receipt->location_id);

        $return = $this->returns->create($request->validated(), $request->user());

        return redirect()
            ->route('purchase-returns.show', $return)
            ->with('success', 'تم حفظ مرتجع المورد كمسودة. رحّله فقط بعد فحص الكميات والدفعات.');
    }

    public function show(PurchaseReturn $purchaseReturn): View
    {
        $this->authorize('view', $purchaseReturn);

        return view('procurement.purchase-returns.show', [
            'purchaseReturn' => $purchaseReturn->load([
                'supplier',
                'goodsReceipt',
                'supplierInvoice',
                'location',
                'currency',
                'returner.employee',
                'poster.employee',
                'items.product',
                'items.goodsReceiptItem',
                'items.inventoryBatch',
            ]),
        ]);
    }

    public function post(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('post', $purchaseReturn);

        $purchaseReturn = $this->returns->post($purchaseReturn, $request->user());

        return redirect()
            ->route('purchase-returns.show', $purchaseReturn)
            ->with('success', 'تم ترحيل مرتجع المورد وتسجيل خروجه من المخزون.');
    }

    public function cancel(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('cancel', $purchaseReturn);

        $purchaseReturn = $this->returns->cancel($purchaseReturn, $request->user());

        return redirect()
            ->route('purchase-returns.show', $purchaseReturn)
            ->with('success', 'تم إلغاء مسودة مرتجع المورد.');
    }
}
