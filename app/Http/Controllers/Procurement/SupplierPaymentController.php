<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\ScopesProcurementLocations;
use App\Http\Requests\Procurement\StoreSupplierPaymentRequest;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\Procurement\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    use ScopesProcurementLocations;

    public function __construct(private readonly SupplierPaymentService $payments)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierPayment::class);
        $locationIds = $this->permittedLocationIds($request->user());

        $payments = SupplierPayment::query()
            ->with(['supplier', 'supplierInvoice', 'location', 'currency', 'paymentMethod', 'creator'])
            ->whereIn('location_id', $locationIds)
            ->when($request->filled('supplier_invoice_id'), fn ($query) => $query->where('supplier_invoice_id', $request->integer('supplier_invoice_id')))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('procurement.supplier-payments.index', compact('payments'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SupplierPayment::class);
        $locationIds = $this->permittedLocationIds($request->user());
        $selectedInvoice = null;

        if ($request->filled('supplier_invoice_id')) {
            $selectedInvoice = SupplierInvoice::query()
                ->with(['supplier', 'location', 'currency'])
                ->whereIn('location_id', $locationIds)
                ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
                ->findOrFail($request->integer('supplier_invoice_id'));
            $this->authorize('view', $selectedInvoice);
        }

        $invoices = SupplierInvoice::query()
            ->with(['supplier', 'location', 'currency'])
            ->whereIn('location_id', $locationIds)
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->orderBy('due_date')
            ->orderByDesc('invoice_date')
            ->get();

        return view('procurement.supplier-payments.create', [
            'selectedInvoice' => $selectedInvoice,
            'invoices' => $invoices,
            'currencies' => Currency::query()->active()->orderBy('code')->get(),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', SupplierPayment::class);
        $invoice = SupplierInvoice::query()->findOrFail($request->integer('supplier_invoice_id'));
        $this->ensurePermittedLocation($request->user(), $invoice->location_id);
        $payment = $this->payments->record($request->validated(), $request->user());

        return redirect()->route('supplier-invoices.show', $payment->supplierInvoice)
            ->with('success', 'تم تسجيل دفعة المورد وتحديث الرصيد المستحق.');
    }
}
