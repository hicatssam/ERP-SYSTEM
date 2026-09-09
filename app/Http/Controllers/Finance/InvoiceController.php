<?php

namespace App\Http\Controllers\Finance;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Invoices\InvoiceService;
use App\Services\Finance\FinancialPostingService;
use App\Notifications\InvoiceCancelledNotification;
use App\Services\Notifications\NotificationDispatcher;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private FinancialPostingService $financialPosting,
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Invoice::with(['location', 'customer', 'issuedBy']);

        if (! $this->canViewAllLocations($user)) {
            $locationId = $user->primaryLocation()?->id;
            abort_unless($locationId, 403, 'لا يوجد موقع رئيسي مرتبط بحسابك.');
            $query->where('location_id', $locationId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('invoice_type')) {
            $query->where('invoice_type', $request->string('invoice_type')->toString());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issued_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issued_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where('invoice_number', 'like', "%{$search}%");
        }

        $invoices = $query->latest('issued_at')->paginate(25)->withQueryString();

        // Also repairs previously created invoices that still contain zero paid.
        $invoices->getCollection()->each(
            fn (Invoice $invoice) => $this->syncInvoicePaymentAmounts($invoice)
        );

        return view('finance.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $this->ensureInvoiceAccess($invoice);
        $this->syncInvoicePaymentAmounts($invoice);
        $invoice->load(['location', 'customer', 'issuedBy', 'items.product', 'cancelledBy']);

        return view('finance.invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $this->ensureInvoiceAccess($invoice);
        $this->syncInvoicePaymentAmounts($invoice);
        $invoice->load(['location', 'customer', 'issuedBy', 'items.product']);

        return view('finance.invoices.print', compact('invoice'));
    }

    public function downloadPdf(Invoice $invoice)
    {
        $this->ensureInvoiceAccess($invoice);
        $this->syncInvoicePaymentAmounts($invoice);
        $invoice->load(['location', 'customer', 'issuedBy', 'items.product']);

        $html = view('pdf.invoices.template', compact('invoice'))->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 12,
            'margin_right' => 12,
            'directionality' => 'rtl',
        ]);

        $mpdf->SetTitle($invoice->invoice_number);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($invoice->invoice_number . '.pdf', 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header(
                'Content-Disposition',
                'attachment; filename="' . $invoice->invoice_number . '.pdf"'
            );
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->ensureInvoiceAccess($invoice);

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5'],
        ]);

        if (! $invoice->isActive()) {
            return back()->with('error', 'الفاتورة ملغاة بالفعل.');
        }

        $this->invoiceService->syncPaymentAmounts($invoice);
        $invoice->refresh();

        if ((float) $invoice->paid_amount > 0.004) {
            return back()->with('error', 'لا يمكن إلغاء فاتورة عليها تحصيل. استرد المبلغ أولًا.');
        }

        $source = $invoice->order()->first();
        $sourceStatus = $source?->status instanceof BackedEnum
            ? $source->status->value
            : (string) ($source?->status ?? '');
        if ($source && ! in_array($sourceStatus, ['cancelled', 'rejected'], true)) {
            return back()->with('error', 'ألغِ الطلب الأصلي أولًا حتى يبقى المخزون والفاتورة متطابقين.');
        }

        DB::transaction(function () use ($invoice, $validated): void {
            // Any customer-account allocations become available credit again.
            $invoice->customerPaymentAllocations()->delete();

            $invoice->update([
                'status' => 'cancelled',
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['cancellation_reason'],
            ]);
            $this->financialPosting->saleCancellation($invoice, Auth::user());
        });

        NotificationDispatcher::notifyByPermissions(
            new InvoiceCancelledNotification($invoice->fresh(), $validated['cancellation_reason']),
            ['invoices.view', 'financial.branch.view', 'financial.collections.view'],
            $invoice->location_id,
            ['financial.global.view'],
            Auth::id(),
        );

        return back()->with('success', 'تم إلغاء الفاتورة وإعادة أي دفعة حساب مخصصة لها إلى رصيد العميل الدائن.');
    }

    private function canViewAllLocations($user): bool
    {
        return $user->isAdmin() || $user->can('financial.global.view');
    }

    private function ensureInvoiceAccess(Invoice $invoice): void
    {
        $user = Auth::user();

        if ($this->canViewAllLocations($user)) {
            return;
        }

        $locationId = $user->primaryLocation()?->id;

        abort_unless(
            $locationId && (int) $invoice->location_id === (int) $locationId,
            403,
            'لا يمكنك الوصول إلى فاتورة تابعة لفرع آخر.'
        );
    }

    /**
     * Keep invoice pages compatible with both the old and the new service.
     *
     * The fallback prevents a 500 response when InvoiceController.php is copied
     * before InvoiceService.php, while still repairing the payment totals.
     */
    private function syncInvoicePaymentAmounts(Invoice $invoice): void
    {
        if (method_exists($this->invoiceService, 'syncPaymentAmounts')) {
            $this->invoiceService->syncPaymentAmounts($invoice);

            return;
        }

        $orderType = $invoice->order_type instanceof BackedEnum
            ? (string) $invoice->order_type->value
            : (string) $invoice->order_type;

        if ($orderType !== OrderType::Order->value) {
            return;
        }

        $totalAmount = round(max(0, (float) $invoice->total_amount), 2);
        $paidAmount = round((float) Payment::query()
            ->where('order_type', OrderType::Order->value)
            ->where('order_id', $invoice->order_id)
            ->where('status', PaymentStatus::Confirmed->value)
            ->sum('amount'), 2);

        $paidAmount = min(max(0, $paidAmount), $totalAmount);
        $remainingAmount = round(max(0, $totalAmount - $paidAmount), 2);

        if (
            round((float) $invoice->paid_amount, 2) !== $paidAmount
            || round((float) $invoice->remaining_amount, 2) !== $remainingAmount
        ) {
            $invoice->updateQuietly([
                'paid_amount' => number_format($paidAmount, 2, '.', ''),
                'remaining_amount' => number_format($remainingAmount, 2, '.', ''),
            ]);
        }
    }
}
