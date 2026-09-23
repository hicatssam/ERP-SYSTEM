<?php

namespace App\Http\Controllers\Sales;
use App\Http\Controllers\Controller;
use App\Enums\PaymentArrangement;
use App\Http\Requests\Sales\CancelOrderRequest;
use App\Http\Requests\Sales\StoreOrderRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\LocationPaymentAccount;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderService;
use App\Services\Restaurant\RestaurantTableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\SalesChannel;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private RestaurantTableService $restaurantTables
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::query()
            ->with([
                'location',
                'customer',
                'creator',
                'restaurantTable.area',
                'waiter.employee',
            ]);

        /*
         * الأدمن يرى طلبات جميع الفروع.
         * باقي المستخدمين يرون طلبات موقعهم الرئيسي فقط.
         */
        if (! $user->isAdmin()) {
            $locationId = $user->primaryLocation()?->id;

            abort_unless(
                $locationId,
                403,
                'لا يوجد موقع رئيسي مرتبط بحسابك.'
            );

            $query->where('location_id', $locationId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('service_type')) {
            $query->where(
                'restaurant_service_type',
                $request->string('service_type')->toString()
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date('date_to')
            );
        }

        $orders = $query
            ->latest()
            ->paginate(25)
            ->withQueryString();



            $salesChannels = SalesChannel::query()
    ->active()
    ->orderBy('sort_order')
    ->orderBy('name')
    ->get();

        return view('sales.orders.index', compact('orders','salesChannels'));
    }

 public function create()
{
    $user = Auth::user();
    $location = $user->primaryLocation();

    abort_unless(
        $location && $location->isBranch(),
        403,
        'لا يوجد فرع رئيسي صالح مرتبط بحسابك.'
    );

    // العميل المركزي يظهر في كل فرع مسموح له بالسحب منه.
    $customers = Customer::query()
        ->availableAt((int) $location->id)
        ->with(['location:id,name', 'locations:id,name'])
        ->withSum([
            'invoices as account_outstanding' => fn ($query) => $query
                ->where('status', 'active')
                ->where('location_id', $location->id),
        ], 'remaining_amount')
        ->orderByRaw("CASE WHEN customer_type = 'individual' THEN 1 ELSE 0 END")
        ->orderBy('name')
        ->get();

    $paymentMethods = PaymentMethod::query()
        ->active()
        ->orderBy('name')
        ->get();

    $salesChannels = SalesChannel::query()
        ->active()
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();

    return view('sales.orders.create', compact(
        'location',
        'customers',
        'paymentMethods',
        'salesChannels'
    ));
}

   public function store(StoreOrderRequest $request)
{
    Gate::authorize('create', Order::class);

    $user = $request->user();
    $location = $this->requiredPrimaryBranch($user);

    $data = $request->validated();

    /*
    |--------------------------------------------------------------------------
    | الفرع
    |--------------------------------------------------------------------------
    */
    $data['location_id'] = $location->id;

    $quickSale = $request->boolean('quick_sale');

    /*
    |--------------------------------------------------------------------------
    | البيع السريع
    |--------------------------------------------------------------------------
    |
    | البيع السريع لا يعني نقدي فقط.
    | هو عميل غير مسجل + دفع مباشر، وطريقة الدفع يمكن أن تكون:
    | نقدي / تحويل بنكي / محفظة / أي طريقة دفع مفعلة.
    |
    */
    if ($quickSale) {
        $data['customer_id'] = null;
        $data['payment_arrangement'] = 'pay_now';

        if (empty($data['payment_method_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method_id' => 'يجب اختيار طريقة الدفع للبيع السريع.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | التحقق من العميل
    |--------------------------------------------------------------------------
    */
    $this->ensureCustomerAvailableAtLocation(
        $data['customer_id'] ?? null,
        (int) $location->id
    );

    /*
    |--------------------------------------------------------------------------
    | إثبات الدفع
    |--------------------------------------------------------------------------
    */
    $data['payment_proof'] = $request->file('payment_proof');

    /*
    |--------------------------------------------------------------------------
    | طريقة الدفع
    |--------------------------------------------------------------------------
    */
    $paymentMethod = null;

    if (! empty($data['payment_method_id'])) {
        $paymentMethod = \App\Models\PaymentMethod::query()
            ->active()
            ->findOrFail($data['payment_method_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | التحقق من متطلبات طريقة الدفع
    |--------------------------------------------------------------------------
    */
    if ($quickSale && $paymentMethod) {

        if (
            $paymentMethod->requires_reference
            && empty($data['reference_number'])
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reference_number' => 'يجب إدخال رقم عملية الدفع أو الحوالة.',
            ]);
        }

        if (
            $paymentMethod->requires_verification
            && ! $request->hasFile('payment_proof')
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_proof' => 'يجب إرفاق صورة إثبات الدفع لهذه الطريقة.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | هل نؤكد البيع السريع مباشرة؟
    |--------------------------------------------------------------------------
    |
    | نقدي / طريقة لا تحتاج تحقق:
    | نعم.
    |
    | تحويل بنكي يحتاج تحقق:
    | لا، يبقى الطلب بانتظار التحقق.
    |
    */
    $requiresVerification =
        $quickSale
        && $paymentMethod
        && (bool) $paymentMethod->requires_verification;

    $autoConfirm =
        $quickSale
        && ! $requiresVerification;

    /*
    |--------------------------------------------------------------------------
    | إنشاء الطلب
    |--------------------------------------------------------------------------
    */
    $order = $this->orderService->createOrder(
        $data,
        $user,
        $autoConfirm
    );

    /*
    |--------------------------------------------------------------------------
    | بيع سريع يحتاج تحقق
    |--------------------------------------------------------------------------
    */
    if ($quickSale && $requiresVerification) {
        return redirect()
            ->route('orders.show', $order)
            ->with(
                'success',
                'تم تسجيل عملية الدفع بنجاح وهي بانتظار التحقق. لم يتم خصم المخزون أو إصدار الفاتورة بعد.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | بيع سريع مؤكد
    |--------------------------------------------------------------------------
    */
    if ($quickSale && $order->invoice) {
        return redirect()
            ->route('invoices.print', $order->invoice)
            ->with(
                'success',
                'تم تنفيذ البيع السريع وخصم المخزون وإنشاء الفاتورة.'
            );
    }

    return redirect()
        ->route('orders.show', $order)
        ->with(
            'success',
            'تم إنشاء الطلب بنجاح.'
        );
}


    public function show(Request $request, Order $order)
    {
        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        $order->load([
            'location',
            'customer',
            'creator',
            'items.product',
            'invoice',
            'payments.paymentMethod',
            'payments.locationPaymentAccount',
            'payments.receivedBy',
            'payments.verifiedBy',
            'restaurantTable.area',
            'restaurantTableSession',
            'waiter.employee',
            'salesChannel',
            'kitchenTickets.station',
            'kitchenTickets.items',
        ]);

        $bankPaymentMethods = PaymentMethod::query()
            ->active()
            ->whereIn('type', [
                'bank_transfer',
                'electronic_wallet',
            ])
            ->where(function ($query) use ($order): void {
                $query
                    ->whereDoesntHave('locationPaymentMethods')
                    ->orWhereHas(
                        'locationPaymentMethods',
                        function ($assignment) use ($order): void {
                            $assignment
                                ->where(
                                    'location_id',
                                    $order->location_id
                                )
                                ->where('is_active', true);
                        }
                    );
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $bankPaymentAccounts = LocationPaymentAccount::query()
            ->with('paymentMethod')
            ->where('location_id', $order->location_id)
            ->where('is_active', true)
            ->whereHas(
                'paymentMethod',
                function ($query): void {
                    $query
                        ->active()
                        ->whereIn('type', [
                            'bank_transfer',
                            'electronic_wallet',
                        ]);
                }
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('sales.orders.show', compact(
            'order',
            'bankPaymentMethods',
            'bankPaymentAccounts'
        ));
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        $order->load([
            'location',
            'items.product',
        ]);

        /*
         * حتى الأدمن عندما يعدل طلبًا، يجب أن يرى عملاء
         * فرع الطلب نفسه فقط.
         */
        $customers = Customer::query()
            ->availableAt((int) $order->location_id)
            ->orderBy('name')
            ->get([
                'id',
                'location_id',
                'name',
                'phone',
            ]);

        return view('sales.orders.edit', compact(
            'order',
            'customers'
        ));
    }

    public function update(
        Request $request,
        Order $order
    ) {
        $this->authorize('update', $order);

        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        $data = $request->validate([
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id'),
            ],

            'payment_arrangement' => [
                'required',
                Rule::in(array_map(
                    fn (PaymentArrangement $arrangement): string => $arrangement->value,
                    PaymentArrangement::cases()
                )),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items' => [
                'nullable',
                'array',
            ],

            'items.*.id' => [
                'required_with:items',
                'integer',

                /*
                 * منع إرسال معرف عنصر تابع لطلب آخر.
                 */
                Rule::exists('order_items', 'id')
                    ->where(function ($query) use ($order) {
                        $query->where(
                            'order_id',
                            $order->id
                        );
                    }),
            ],

            'items.*.quantity' => [
                'required_with:items',
                'numeric',
                'min:0.001',
            ],

            'items.*.kitchen_notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $this->ensureCustomerAvailableAtLocation(
            $data['customer_id'] ?? null,
            (int) $order->location_id
        );

        $this->orderService->updateOrder(
            $order,
            $data,
            $request->user()
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'تم تحديث الطلب بنجاح.');
    }

    public function destroy(
        Request $request,
        Order $order
    ) {
        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        return back()->with(
            'error',
            'لا يمكن حذف الطلبات.'
        );
    }

    public function confirm(
        Request $request,
        Order $order
    ) {
        $this->authorize('confirm', $order);

        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        $this->orderService->confirmOrder(
            $order,
            $request->user()
        );

        return back()->with(
            'success',
            'تم تأكيد الطلب وخصم المخزون بنجاح.'
        );
    }

    public function cancel(
        CancelOrderRequest $request,
        Order $order
    ) {
        $this->authorize('cancel', $order);

        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        $updatedOrder = $this->orderService->cancelOrder(
            $order,
            $request->validated('cancellation_reason'),
            $request->user()
        );

        $this->releaseCustomerMenuTableIfIdle(
            $updatedOrder,
            $request->user()
        );

        return back()->with(
            'success',
            'تم إلغاء الطلب.'
        );
    }

    public function complete(
        Request $request,
        Order $order
    ) {
        $this->authorize('complete', $order);

        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        $updatedOrder = $this->orderService->completeOrder(
            $order,
            $request->user()
        );

        $this->releaseCustomerMenuTableIfIdle(
            $updatedOrder,
            $request->user()
        );

        return back()->with(
            'success',
            'تم إكمال الطلب.'
        );
    }

    public function updateCustomerMessage(
        Request $request,
        Order $order
    ) {
        $this->authorize('update', $order);

        $this->ensureOrderAccess(
            $order,
            $request->user()
        );

        abort_unless(
            $order->isCustomerMenuOrder(),
            404
        );

        $data = $request->validate([
            'customer_status_message' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $message = trim(
            (string) ($data['customer_status_message'] ?? '')
        );

        $order->forceFill([
            'customer_status_message' => $message !== '' ? $message : null,
            'customer_status_message_updated_at' => now(),
            'customer_status_message_by' => $request->user()->id,
        ])->save();

        return back()->with(
            'success',
            $message !== ''
                ? 'تم تحديث رسالة العميل وستظهر في شاشة التتبع.'
                : 'تم مسح رسالة العميل.'
        );
    }

    /**
     * A table reserved by the public customer menu becomes available again
     * after its last open customer-menu order is completed or cancelled.
     *
     * We intentionally do not force-close a session that later gained another
     * open order from POS/waiter operations.
     */
    private function releaseCustomerMenuTableIfIdle(
        Order $order,
        User $user
    ): void {
        if (
            ! $order->isCustomerMenuOrder()
            || ! $order->restaurant_table_id
            || ! $order->restaurant_table_session_id
        ) {
            return;
        }

        $session = $order->restaurantTableSession()
            ->first();

        if (! $session || ! $session->isOpen()) {
            return;
        }

        $hasOpenOrders = $session->orders()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($hasOpenOrders) {
            return;
        }

        $table = $order->restaurantTable()
            ->first();

        if (! $table) {
            return;
        }

        try {
            $closed = $this->restaurantTables->close(
                $table,
                $user
            );

            $this->restaurantTables->notifyClosed($closed);
        } catch (ValidationException) {
            /*
             * A concurrent POS/waiter operation may have attached another
             * order to the same session. In that case keep the table occupied.
             */
        }
    }


    /**
     * إرجاع الفرع الرئيسي للمستخدم أو منع العملية.
     */
    private function requiredPrimaryBranch(
        User $user
    ): Location {
        $location = $user->primaryLocation();

        abort_unless(
            $location && $location->isBranch(),
            403,
            'لا يوجد فرع رئيسي مرتبط بحسابك.'
        );

        return $location;
    }

    /**
     * حماية الطلب من الوصول المباشر بواسطة موظف فرع آخر.
     */
    private function ensureOrderAccess(
        Order $order,
        User $user
    ): void {
       if (
    $user->isAdmin()
    || $user->can('financial.global.view')
) {
    return;
}

        $locationId = $user->primaryLocation()?->id;

        abort_unless(
            $locationId &&
            (int) $order->location_id === (int) $locationId,
            403,
            'لا يمكنك الوصول إلى طلب تابع لفرع آخر.'
        );
    }

    /**
     * التأكد من أن العميل تابع لنفس فرع الطلب.
     */
    private function ensureCustomerAvailableAtLocation(
        mixed $customerId,
        int $locationId
    ): void {
        if (blank($customerId)) {
            return;
        }

        $customer = Customer::query()
            ->with('locations')
            ->find($customerId);

        if (! $customer || ! $customer->isAvailableAt($locationId)) {
            throw ValidationException::withMessages([
                'customer_id' =>
                    'العميل المحدد غير متاح للشراء من هذا الفرع.',
            ]);
        }
    }



    public function liveList(Request $request)
{
    $user = $request->user();

    $query = Order::query()
        ->with([
            'location',
            'customer',
            'creator',
        ]);

    if (! $user->isAdmin()) {
        $locationId = $user->primaryLocation()?->id;

        abort_unless($locationId, 403);

        $query->where('location_id', $locationId);
    }

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    $orders = $query
        ->latest()
        ->paginate(25)
        ->withQueryString();

    return view(
        'sales.orders.partials.table',
        compact('orders')
    );
}
}
