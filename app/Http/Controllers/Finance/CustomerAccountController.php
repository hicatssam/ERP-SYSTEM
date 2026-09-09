<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Services\Customers\CustomerAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mpdf\Mpdf;

class CustomerAccountController extends Controller
{
    public function __construct(
        private CustomerAccountService $accountService,
    ) {}

    public function statement(Request $request, Customer $customer)
    {
        $this->ensureCustomerAccess($customer, $request);

        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $statement = $this->accountService->statement(
            $customer,
            $request->user(),
            $filters
        );

        $summary = $this->accountService->summary(
            $customer,
            $request->user(),
            $statement['location_id']
        );

        $openInvoices = $customer->invoices()
            ->with('location:id,name')
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->when(
                $statement['location_id'] !== null,
                fn ($query) => $query->where('location_id', $statement['location_id'])
            )
            ->orderByRaw('COALESCE(due_at, issued_at) asc')
            ->get();

        $customerPayments = $customer->customerPayments()
            ->with([
                'location:id,name',
                'paymentMethod:id,name,name_ar',
                'receivedBy:id,username',
                'allocations.invoice:id,invoice_number,location_id',
            ])
            ->when(
                $statement['location_id'] !== null,
                function ($query) use ($statement): void {
                    $locationId = (int) $statement['location_id'];

                    $query->where(function ($paymentQuery) use ($locationId): void {
                        $paymentQuery
                            ->where('allocation_payload->scope_location_id', $locationId)
                            ->orWhereHas('allocations.invoice', function ($invoiceQuery) use ($locationId): void {
                                $invoiceQuery->where('location_id', $locationId);
                            });
                    });
                }
            )
            ->latest('paid_at')
            ->limit(20)
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->active()
            ->orderBy('sort_order')
            ->get();

        $locations = $this->accountService->canViewAllTransactions($request->user())
            ? Location::branches()->active()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('sales.customers.statement', compact(
            'customer',
            'statement',
            'summary',
            'openInvoices',
            'customerPayments',
            'paymentMethods',
            'locations'
        ));
    }

    public function pdf(Request $request, Customer $customer)
    {
        $this->ensureCustomerAccess($customer, $request);

        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $statement = $this->accountService->statement(
            $customer,
            $request->user(),
            $filters
        );

        $summary = $this->accountService->summary(
            $customer,
            $request->user(),
            $statement['location_id']
        );

        $selectedLocation = $statement['location_id']
            ? Location::find($statement['location_id'])
            : null;

        $html = view('pdf.customers.statement', compact(
            'customer',
            'statement',
            'summary',
            'selectedLocation'
        ))->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
            'directionality' => 'rtl',
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle('كشف حساب - ' . $customer->name);
        $mpdf->WriteHTML($html);

        $filename = 'customer-statement-' . $customer->id . '-' . now()->format('Ymd') . '.pdf';

        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function storePayment(Request $request, Customer $customer)
    {
        abort_unless(
            $request->user()->can('payments.record'),
            403,
            'ليس لديك صلاحية لتسجيل الدفعات.'
        );

        $this->ensureCustomerAccess($customer, $request);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where('is_active', true),
            ],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'scope_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'allocation_mode' => ['required', Rule::in(['automatic', 'manual'])],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer', 'distinct', 'exists:invoices,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $method = PaymentMethod::findOrFail($data['payment_method_id']);

        if (($method->requires_reference || $method->requires_verification)
            && blank($data['reference_number'] ?? null)) {
            return back()
                ->withErrors(['reference_number' => 'رقم العملية / الحوالة مطلوب لطريقة الدفع المحددة.'])
                ->withInput();
        }

        if ($method->requires_verification && ! $request->hasFile('payment_proof')) {
            return back()
                ->withErrors(['payment_proof' => 'إثبات الدفع مطلوب لطريقة الدفع المحددة.'])
                ->withInput();
        }

        $proofPath = $request->hasFile('payment_proof')
            ? $request->file('payment_proof')->store('customer-payment-proofs', 'public')
            : null;

        $data['payment_proof_path'] = $proofPath;

        $payment = $this->accountService->recordPayment(
            $customer,
            $data,
            $request->user()
        );

        $message = $payment->isPendingVerification()
            ? 'تم تسجيل الدفعة وهي بانتظار التحقق.'
            : 'تم تسجيل الدفعة وتوزيعها على الفواتير بنجاح.';

        return redirect()
            ->route('customers.statement', [
                'customer' => $customer,
                'location_id' => $data['scope_location_id'] ?? null,
            ])
            ->with('success', $message);
    }

    public function verifyPayment(
        Request $request,
        Customer $customer,
        CustomerPayment $customerPayment
    ) {
        abort_unless(
            $request->user()->can('payments.verify'),
            403,
            'ليس لديك صلاحية للتحقق من الدفعات.'
        );

        $this->ensureCustomerAccess($customer, $request);

        abort_unless(
            (int) $customerPayment->customer_id === (int) $customer->id,
            404
        );

        $this->ensureCustomerPaymentAccess($customerPayment, $request);

        $data = $request->validate([
            'action' => ['required', Rule::in(['verify', 'reject'])],
            'rejection_reason' => ['nullable', 'required_if:action,reject', 'string', 'min:3', 'max:1000'],
        ]);

        $this->accountService->verifyPayment(
            $customerPayment,
            $request->user(),
            $data['action'],
            $data['rejection_reason'] ?? null
        );

        return back()->with(
            'success',
            $data['action'] === 'verify'
                ? 'تم تأكيد الدفعة وتوزيعها على الفواتير.'
                : 'تم رفض الدفعة.'
        );
    }

    private function ensureCustomerPaymentAccess(
        CustomerPayment $customerPayment,
        Request $request
    ): void {
        $user = $request->user();

        if ($this->accountService->canViewAllTransactions($user)) {
            return;
        }

        $locationId = $user->primaryLocation()?->id;
        $scopeLocationId = $customerPayment->allocation_payload['scope_location_id'] ?? null;

        abort_unless(
            $locationId !== null
                && $scopeLocationId !== null
                && (int) $scopeLocationId === (int) $locationId,
            403,
            'لا يمكنك التحقق من دفعة تخص فرعًا آخر أو دفعة مركزية.'
        );
    }

    private function ensureCustomerAccess(Customer $customer, Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->can('customers.view')
                || $user->can('customers.view_all')
                || $user->can('financial.global.view'),
            403,
            'ليس لديك صلاحية لعرض حسابات العملاء.'
        );

        abort_unless(
            $customer->canBeAccessedBy($user),
            403,
            'لا يمكنك الوصول إلى هذا العميل.'
        );
    }
}
