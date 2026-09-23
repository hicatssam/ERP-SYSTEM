<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CorrectPaymentRequest;
use App\Http\Requests\Finance\RefundPaymentRequest;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Http\Requests\Finance\VerifyPaymentRequest;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\SpecialCakeOrder;
use App\Services\ActivityLogger;
use App\Services\Invoices\InvoiceService;
use App\Services\Payments\PaymentService;
use App\Services\Finance\FinancialPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private InvoiceService $invoiceService,
        private FinancialPostingService $financialPosting,
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless(
            $user->isAdmin()
            || $user->canAny([
                'payments.record',
                'payments.verify',
                'payments.correct',
                'payments.refund',
                'financial.branch.view',
                'financial.global.view',
                'financial.collections.view',
            ]),
            403
        );

        $canViewAll = $user->isAdmin()
            || $user->can('financial.global.view');

        $locationIds = $canViewAll
            ? Location::pluck('id')
            : collect([$user->primaryLocation()?->id])->filter();

        /*
        |--------------------------------------------------------------------------
        | كل Payment هو حركة تحصيل أصلية
        |--------------------------------------------------------------------------
        |
        | Refund لا يستبدل Payment ولا يغير مبلغها الأصلي.
        | يتم تحميل الاستردادات وإظهارها كسطور مالية مستقلة تحت الدفعة.
        |
        */
        $query = Payment::with([
            'paymentMethod',
            'locationPaymentAccount',
            'location',
            'receivedBy',
            'verifiedBy',
            'refunds.paymentMethod',
            'refunds.processedBy',
        ])->whereIn('location_id', $locationIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method_id')) {
            $query->where(
                'payment_method_id',
                $request->payment_method_id
            );
        }

        if ($request->filled('location_id')) {
            $query->where(
                'location_id',
                $request->location_id
            );
        }

        if ($request->filled('order_type')) {
            $query->where(
                'order_type',
                $request->order_type
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'paid_at',
                '>=',
                $request->date_from
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'paid_at',
                '<=',
                $request->date_to
            );
        }

        $payments = $query
            ->latest('paid_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | ربط الحركات بالطلب والفاتورة والعميل
        |--------------------------------------------------------------------------
        */
        $this->attachFinancialSources(
            $payments->getCollection()
        );

        $paymentMethods = PaymentMethod::active()
            ->orderBy('sort_order')
            ->get();

        $locations = $canViewAll
            ? Location::active()->get()
            : collect();

        $statusOptions =
            \App\Enums\PaymentStatus::cases();

        return view(
            'finance.payments.index',
            compact(
                'payments',
                'paymentMethods',
                'locations',
                'statusOptions'
            )
        );
    }

    public function bankSales(Request $request)
    {
        $user = Auth::user();

        abort_unless(
            $user->isAdmin()
            || $user->canAny([
                'payments.record',
                'payments.verify',
                'payments.correct',
                'payments.refund',
                'financial.branch.view',
                'financial.global.view',
                'financial.collections.view',
            ]),
            403
        );

        $canViewAll = $user->isAdmin()
            || $user->can('financial.global.view');

        $locationIds = $canViewAll
            ? Location::query()->pluck('id')
            : collect([$user->primaryLocation()?->id])->filter();

        $query = Payment::query()
            ->with([
                'paymentMethod',
                'locationPaymentAccount',
                'location',
                'receivedBy',
                'verifiedBy',
            ])
            ->whereIn('location_id', $locationIds)
            ->where(function ($bankingQuery): void {
                $bankingQuery
                    ->whereNotNull('location_payment_account_id')
                    ->orWhereHas('paymentMethod', function ($methodQuery): void {
                        $methodQuery->whereIn('type', [
                            'bank_transfer',
                            'electronic_wallet',
                        ]);
                    });
            });

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('payment_method_id')) {
            $query->where(
                'payment_method_id',
                $request->integer('payment_method_id')
            );
        }

        if ($request->filled('location_payment_account_id')) {
            $query->where(
                'location_payment_account_id',
                $request->integer('location_payment_account_id')
            );
        }

        if ($request->filled('location_id')) {
            $query->where(
                'location_id',
                $request->integer('location_id')
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'paid_at',
                '>=',
                $request->date('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'paid_at',
                '<=',
                $request->date('date_to')
            );
        }

        $search = trim(
            $request->string('search')->toString()
        );

        if ($search !== '') {
            $like = '%' . $search . '%';

            $normalOrderIds = Order::query()
                ->whereIn('location_id', $locationIds)
                ->where(function ($orderQuery) use ($like): void {
                    $orderQuery
                        ->where('order_number', 'like', $like)
                        ->orWhereHas('customer', function ($customerQuery) use ($like): void {
                            $customerQuery
                                ->where('name', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        });
                })
                ->pluck('id');

            $cakeOrderIds = SpecialCakeOrder::query()
                ->whereIn('origin_branch_id', $locationIds)
                ->where(function ($orderQuery) use ($like): void {
                    $orderQuery
                        ->where('order_number', 'like', $like)
                        ->orWhereHas('customer', function ($customerQuery) use ($like): void {
                            $customerQuery
                                ->where('name', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        });
                })
                ->pluck('id');

            $query->where(function ($searchQuery) use (
                $like,
                $normalOrderIds,
                $cakeOrderIds
            ): void {
                $searchQuery
                    ->where('reference_number', 'like', $like)
                    ->orWhere('sender_name', 'like', $like)
                    ->orWhere('sender_phone', 'like', $like)
                    ->orWhere('sender_account_number', 'like', $like)
                    ->orWhereHas('locationPaymentAccount', function ($accountQuery) use ($like): void {
                        $accountQuery
                            ->where('name', 'like', $like)
                            ->orWhere('provider_name', 'like', $like)
                            ->orWhere('account_holder_name', 'like', $like)
                            ->orWhere('account_number', 'like', $like)
                            ->orWhere('iban', 'like', $like)
                            ->orWhere('phone_number', 'like', $like);
                    })
                    ->orWhere(function ($orderPaymentQuery) use ($normalOrderIds): void {
                        $orderPaymentQuery
                            ->where('order_type', 'order')
                            ->whereIn('order_id', $normalOrderIds);
                    })
                    ->orWhere(function ($cakePaymentQuery) use ($cakeOrderIds): void {
                        $cakePaymentQuery
                            ->where('order_type', 'special_cake_order')
                            ->whereIn('order_id', $cakeOrderIds);
                    });
            });
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as transfers_count')
            ->selectRaw("SUM(CASE WHEN status IN ('confirmed', 'corrected', 'refunded') THEN amount ELSE 0 END) as confirmed_total")
            ->selectRaw("SUM(CASE WHEN status = 'pending_verification' THEN amount ELSE 0 END) as pending_total")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_total")
            ->selectRaw("SUM(CASE WHEN payment_proof IS NULL OR payment_proof = '' THEN 1 ELSE 0 END) as missing_proof_count")
            ->first();

        $payments = $query
            ->latest('paid_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $this->attachFinancialSources(
            $payments->getCollection()
        );

        $paymentMethods = PaymentMethod::query()
            ->active()
            ->whereIn('type', [
                'bank_transfer',
                'electronic_wallet',
            ])
            ->orderBy('sort_order')
            ->get();

        $paymentAccounts = LocationPaymentAccount::query()
            ->with('paymentMethod')
            ->whereIn('location_id', $locationIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $locations = $canViewAll
            ? Location::query()->active()->orderBy('name')->get()
            : collect();

        $statusOptions = \App\Enums\PaymentStatus::cases();

        return view(
            'finance.payments.bank-sales',
            compact(
                'payments',
                'paymentMethods',
                'paymentAccounts',
                'locations',
                'statusOptions',
                'summary'
            )
        );
    }

    public function store(StorePaymentRequest $request)
    {
        $this->authorize(
            'record',
            Payment::class
        );

        $this->paymentService->recordPayment(
            $request->validated(),
            Auth::user()
        );

        return back()->with(
            'success',
            'تم تسجيل الدفعة بنجاح.'
        );
    }

    public function verify(
        VerifyPaymentRequest $request,
        Payment $payment
    ) {
        $this->authorize('verify', $payment);
        $validated = $request->validated();
        $approved = $validated['action'] === 'verify';
        $this->paymentService->verify(
            $payment,
            $approved,
            $validated['rejection_reason'] ?? null,
            $request->user()
        );

        return back()->with('success', $approved ? 'تم التحقق من الدفعة وتأكيدها.' : 'تم رفض الدفعة.');
    }

    public function proof(
        Payment $payment
    ): StreamedResponse {
        $this->authorize('view', $payment);

        $path = ltrim(
            str_replace('\\', '/', (string) $payment->payment_proof),
            '/'
        );

        abort_if(
            $path === ''
            || str_contains($path, '..'),
            404,
            'إثبات الدفع غير موجود.'
        );

        $disk = Storage::disk('public');

        abort_unless(
            $disk->exists($path),
            404,
            'ملف إثبات الدفع غير موجود.'
        );

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $downloadName = 'payment-proof-' . $payment->id
            . ($extension !== '' ? '.' . $extension : '');

        return $disk->response(
            $path,
            $downloadName,
            [
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function correct(
        CorrectPaymentRequest $request,
        Payment $payment
    ) {
        $this->authorize(
            'correct',
            $payment
        );

        $validated = $request->validated();
        $this->paymentService->correct(
            $payment,
            (float) $validated['corrected_amount'],
            $validated['reason'],
            $request->user()
        );

        return back()->with(
            'success',
            'تم تصحيح الدفعة.'
        );
    }

    public function refund(
        RefundPaymentRequest $request,
        Payment $payment
    ) {
        $this->authorize(
            'refund',
            $payment
        );

        $validated =
            $request->validated();

        DB::transaction(function () use (
            $validated,
            $payment
        ): void {
            /*
            |--------------------------------------------------------------------------
            | قفل الدفعة
            |--------------------------------------------------------------------------
            |
            | يمنع تنفيذ استردادين متزامنين يتجاوزان مبلغ الدفعة.
            |
            */
            $payment = Payment::query()
                ->with('refunds')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $paymentStatus =
                $payment->status?->value
                ?? (string) $payment->status;

            if (
                ! in_array(
                    $paymentStatus,
                    ['confirmed', 'corrected', 'refunded'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'لا يمكن استرداد دفعة غير مؤكدة.',
                ]);
            }

            $paymentAmount =
                round(
                    (float) $payment->amount,
                    2
                );

            $alreadyRefunded =
                round(
                    (float) $payment
                        ->refunds()
                        ->sum('amount'),
                    2
                );

            $refundableAmount =
                round(
                    max(
                        0,
                        $paymentAmount
                        - $alreadyRefunded
                    ),
                    2
                );

            $requestedRefund =
                round(
                    (float) $validated['amount'],
                    2
                );

            if ($refundableAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'تم استرداد كامل مبلغ هذه الدفعة مسبقًا.',
                ]);
            }

            if (
                $requestedRefund
                >
                $refundableAmount
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        sprintf(
                            'المبلغ المطلوب استرداده أكبر من المبلغ المتاح. المتاح للاسترداد: ₪%.2f.',
                            $refundableAmount
                        ),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | إنشاء حركة الاسترداد
            |--------------------------------------------------------------------------
            |
            | الدفعة الأصلية تبقى بمبلغها الأصلي.
            | الاسترداد يسجل كسجل مستقل في refunds.
            |
            */
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'order_type' => $payment->order_type,
                'order_id' => $payment->order_id,
                'payment_method_id' => $validated['payment_method_id'],
                'amount' => $requestedRefund,
                'reason' => $validated['reason'],
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            $this->financialPosting->refund($refund, $payment, Auth::user());

            $totalRefunded =
                round(
                    $alreadyRefunded
                    + $requestedRefund,
                    2
                );

            /*
            |--------------------------------------------------------------------------
            | حالة الدفعة الأصلية
            |--------------------------------------------------------------------------
            |
            | استرداد جزئي:
            | - المبلغ الأصلي لا يتغير.
            | - الحالة تبقى confirmed / corrected.
            |
            | استرداد كامل:
            | - فقط عندها تتحول إلى refunded.
            |
            */
            if (
                $totalRefunded
                >=
                $paymentAmount
            ) {
                $newPaymentStatus =
                    'refunded';
            } else {
                $newPaymentStatus =
                    $paymentStatus === 'corrected'
                        ? 'corrected'
                        : 'confirmed';
            }

            if (
                $paymentStatus
                !==
                $newPaymentStatus
            ) {
                $payment->update([
                    'status' =>
                        $newPaymentStatus,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | مزامنة الفاتورة
            |--------------------------------------------------------------------------
            |
            | InvoiceService يحسب:
            | صافي المدفوع = الدفعات - الاستردادات.
            |
            */
            $invoice =
                $this->invoiceService
                    ->syncFromPayment(
                        $payment->fresh()
                    );

            /*
            |--------------------------------------------------------------------------
            | مزامنة الحالة المالية للطلب العادي
            |--------------------------------------------------------------------------
            */
            $this->syncSourcePaymentStatus(
                $payment,
                $invoice
            );

            ActivityLogger::log(
                userId: Auth::id(),
                action: 'payment.refunded',
                module: 'payments',
                recordType: 'refunds',
                recordId: $refund->id,
                oldValues: [
                    'payment_amount' =>
                        $paymentAmount,

                    'already_refunded' =>
                        $alreadyRefunded,
                ],
                newValues: [
                    'refund_amount' =>
                        $requestedRefund,

                    'total_refunded' =>
                        $totalRefunded,

                    'net_collected' =>
                        max(
                            0,
                            $paymentAmount
                            - $totalRefunded
                        ),

                    'payment_status' =>
                        $newPaymentStatus,
                ],
                metadata: [
                    'payment_id' =>
                        $payment->id,

                    'location_id' =>
                        $payment->location_id,

                    'order_type' =>
                        $payment->order_type?->value
                        ?? $payment->order_type,

                    'order_id' =>
                        $payment->order_id,

                    'reason' =>
                        $validated['reason'],
                ],
            );
        });

        return back()->with(
            'success',
            'تم تسجيل الاسترداد وتحديث صافي المدفوع بنجاح.'
        );
    }

    /**
     * تحديث payment_status للمستند الأصلي حسب صافي المدفوع في الفاتورة.
     */
    private function syncSourcePaymentStatus(
        Payment $payment,
        ?Invoice $invoice
    ): void {
        $orderType =
            $payment->order_type?->value
            ?? (string) $payment->order_type;

        if (! $invoice || ! in_array($orderType, ['order', 'special_cake_order'], true)) {
            return;
        }

        $order = $orderType === 'order'
            ? Order::query()->find($payment->order_id)
            : SpecialCakeOrder::query()->find($payment->order_id);

        if (! $order) {
            return;
        }

        $paid =
            round(
                (float) $invoice->paid_amount,
                2
            );

        $total =
            round(
                (float) $invoice->total_amount,
                2
            );

        $paymentStatus =
            $paid <= 0
                ? 'payment_pending'
                : (
                    $paid >= $total
                        ? 'paid'
                        : 'partially_paid'
                );

        if (
            ($order->payment_status?->value
                ?? $order->payment_status)
            !==
            $paymentStatus
        ) {
            $order->update([
                'payment_status' =>
                    $paymentStatus,
            ]);
        }
    }

    /**
     * ربط كل Payment في الصفحة بالطلب والفاتورة الحقيقيين.
     *
     * العلاقات runtime:
     * - linkedOrder
     * - linkedInvoice
     */
    private function attachFinancialSources(
        Collection $payments
    ): void {
        if ($payments->isEmpty()) {
            return;
        }

        $normalOrderIds = $payments
            ->filter(
                fn (Payment $payment): bool =>
                    $this->orderTypeValue($payment)
                    === 'order'
            )
            ->pluck('order_id')
            ->filter()
            ->map(
                fn ($id) => (int) $id
            )
            ->unique()
            ->values();

        $cakeOrderIds = $payments
            ->filter(
                fn (Payment $payment): bool =>
                    $this->orderTypeValue($payment)
                    === 'special_cake_order'
            )
            ->pluck('order_id')
            ->filter()
            ->map(
                fn ($id) => (int) $id
            )
            ->unique()
            ->values();

        $orders = $normalOrderIds->isEmpty()
            ? collect()
            : Order::query()
                ->with('customer')
                ->whereIn(
                    'id',
                    $normalOrderIds
                )
                ->get()
                ->keyBy('id');

        $cakeOrders = $cakeOrderIds->isEmpty()
            ? collect()
            : SpecialCakeOrder::query()
                ->with([
                    'customer',
                    'originBranch',
                ])
                ->whereIn(
                    'id',
                    $cakeOrderIds
                )
                ->get()
                ->keyBy('id');

        $invoices =
            collect();

        if (
            $normalOrderIds->isNotEmpty()
            ||
            $cakeOrderIds->isNotEmpty()
        ) {
            $invoiceQuery =
                Invoice::query()
                    ->where(function ($query) use (
                        $normalOrderIds,
                        $cakeOrderIds
                    ): void {
                        if (
                            $normalOrderIds->isNotEmpty()
                        ) {
                            $query->where(
                                function ($normalQuery) use (
                                    $normalOrderIds
                                ): void {
                                    $normalQuery
                                        ->where(
                                            'order_type',
                                            'order'
                                        )
                                        ->whereIn(
                                            'order_id',
                                            $normalOrderIds
                                        );
                                }
                            );
                        }

                        if (
                            $cakeOrderIds->isNotEmpty()
                        ) {
                            if (
                                $normalOrderIds->isNotEmpty()
                            ) {
                                $query->orWhere(
                                    function ($cakeQuery) use (
                                        $cakeOrderIds
                                    ): void {
                                        $cakeQuery
                                            ->where(
                                                'order_type',
                                                'special_cake_order'
                                            )
                                            ->whereIn(
                                                'order_id',
                                                $cakeOrderIds
                                            );
                                    }
                                );
                            } else {
                                $query->where(
                                    function ($cakeQuery) use (
                                        $cakeOrderIds
                                    ): void {
                                        $cakeQuery
                                            ->where(
                                                'order_type',
                                                'special_cake_order'
                                            )
                                            ->whereIn(
                                                'order_id',
                                                $cakeOrderIds
                                            );
                                    }
                                );
                            }
                        }
                    });

            $invoices =
                $invoiceQuery
                    ->get()
                    ->keyBy(
                        function (
                            Invoice $invoice
                        ): string {
                            $type =
                                $invoice
                                    ->order_type
                                    ?->value
                                ??
                                $invoice
                                    ->order_type;

                            return
                                (string) $type
                                . ':'
                                . (int)
                                    $invoice
                                        ->order_id;
                        }
                    );
        }

        foreach (
            $payments
            as $payment
        ) {
            $orderType =
                $this->orderTypeValue(
                    $payment
                );

            $linkedOrder =
                match ($orderType) {
                    'order' =>
                        $orders->get(
                            (int)
                                $payment
                                    ->order_id
                        ),

                    'special_cake_order' =>
                        $cakeOrders->get(
                            (int)
                                $payment
                                    ->order_id
                        ),

                    default =>
                        null,
                };

            $linkedInvoice =
                $invoices->get(
                    $orderType
                    . ':'
                    . (int)
                        $payment
                            ->order_id
                );

            $payment->setRelation(
                'linkedOrder',
                $linkedOrder
            );

            $payment->setRelation(
                'linkedInvoice',
                $linkedInvoice
            );
        }
    }

    private function orderTypeValue(
        Payment $payment
    ): string {
        return (string) (
            $payment->order_type?->value
            ?? $payment->order_type
            ?? ''
        );
    }
}
