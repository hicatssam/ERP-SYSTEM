<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\PaymentArrangement;
use App\Enums\RestaurantServiceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Requests\Restaurant\StoreRestaurantPosOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SalesChannel;
use App\Services\Restaurant\RestaurantContextService;
use App\Services\Restaurant\RestaurantPosCatalogService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RestaurantPosController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
        private readonly RestaurantOrderService $restaurantOrders,
        private readonly RestaurantPosCatalogService $catalog,
    ) {
    }

    public function printInvoice(Request $request, Order $order, InvoiceController $invoices)
    {
        $this->authorizePosPrint($request, $order);

        return $invoices->print($order->invoice()->firstOrFail());
    }

    public function printKitchen(Request $request, Order $order)
    {
        $this->authorizePosPrint($request, $order);
        $order->load(['location', 'restaurantTable', 'kitchenTickets.station', 'kitchenTickets.items']);
        abort_unless($order->kitchenTickets->isNotEmpty(), 404);

        return view('restaurant.pos.kitchen-print', compact('order'));
    }

    private function authorizePosPrint(Request $request, Order $order): void
    {
        $location = $this->context->resolveLocation($request->user(), (int) $order->location_id);
        abort_unless($order->isRestaurantOrder()
            && (int) $order->location_id === (int) $location->id
            && ((int) $order->created_by === (int) $request->user()->id
                || $request->user()->can('invoices.view')), 403);
    }

    public function index(Request $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations(
            $request->user()
        );

        $firstPage = $this->catalog->page($location->id);
        $facets = $this->catalog->facets($location->id);

        $customers = Customer::query()
            ->availableAt($location->id)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'phone',
            ]);

        $paymentMethods = PaymentMethod::query()
            ->active()
            ->where(function ($query) use ($location): void {
                $query->whereDoesntHave('locationPaymentMethods')
                    ->orWhereHas('locationPaymentMethods', fn ($assignment) => $assignment
                        ->where('location_id', $location->id)
                        ->where('is_active', true));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $salesChannels = SalesChannel::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $tables = RestaurantTable::query()
            ->forLocation($location->id)
            ->active()
            ->with([
                'area:id,name',
                'activeSession',
            ])
            ->orderBy('area_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        $recentOrders = Order::query()
            ->where('location_id', $location->id)
            ->whereNotNull('restaurant_service_type')
            ->with([
                'restaurantTable.area',
                'customer',
            ])
            ->latest()
            ->limit(8)
            ->get();

        return view(
            'restaurant.pos.index',
            [
                'location' => $location,
                'locations' => $locations,
                'productPayload' => $this->catalog->payload($firstPage->getCollection()),
                'catalogTotal' => $firstPage->total(),
                'catalogHasMore' => $firstPage->hasMorePages(),
                'catalogFacets' => $facets,
                'customers' => $customers,
                'paymentMethods' => $paymentMethods,
                'salesChannels' => $salesChannels,
                'channelDiscountRules' => $salesChannels->mapWithKeys(fn ($channel) => [
                    $channel->id => [
                        'discount_type' => $channel->discount_type?->value,
                        'discount_value' => (float) $channel->discount_value,
                    ],
                ]),
                'tables' => $tables,
                'recentOrders' => $recentOrders,
                'serviceTypes' => RestaurantServiceType::cases(),
                'paymentArrangements' => PaymentArrangement::cases(),
            ]
        );
    }

    public function catalog(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        if (isset($data['ids'])) {
            return response()->json([
                'products' => $this->catalog->payload(
                    $this->catalog->byIds($location->id, $data['ids'])
                ),
            ]);
        }

        $page = $this->catalog->page(
            $location->id,
            (int) ($data['page'] ?? 1),
            $data['q'] ?? null,
            isset($data['category_id']) ? (int) $data['category_id'] : null,
            isset($data['brand_id']) ? (int) $data['brand_id'] : null,
        );

        return response()->json([
            'products' => $this->catalog->payload($page->getCollection()),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function qrOrders(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $location = $this->context->resolveLocation(
            $request->user(), $request->integer('location_id') ?: null
        );
        $orders = Order::query()
            ->where('location_id', $location->id)
            ->where('order_source', 'customer_menu')
            ->with(['customer', 'restaurantTable', 'items'])
            ->when(trim($data['q'] ?? '') !== '', fn ($query) => $query
                ->where(fn ($search) => $search
                    ->where('order_number', 'like', '%'.trim($data['q']).'%')
                    ->orWhere('guest_name', 'like', '%'.trim($data['q']).'%')
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', '%'.trim($data['q']).'%'))))
            ->latest()
            ->paginate(20, ['*'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'orders' => $orders->getCollection()->map(fn (Order $order) => [
                'number' => $order->order_number,
                'status' => $order->status?->label() ?? $order->statusValue(),
                'service' => $order->restaurant_service_type?->label() ?? 'طلب QR',
                'customer' => $order->customer?->name ?: $order->guest_name ?: 'عميل نقدي',
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'quantity' => (float) $item->quantity,
                ])->values(),
                'url' => $request->user()->can('orders.view')
                    ? route('orders.show', $order)
                    : null,
            ])->values(),
            'has_more' => $orders->hasMorePages(),
        ]);
    }

    public function store(
        StoreRestaurantPosOrderRequest $request
    ) {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $request->validated();

        $action = $data['pos_action'] ?? 'bill_payment';
        if ($action === 'kot_print') {
            if (! app(ModuleService::class)->isEnabled('kitchen')) {
                throw ValidationException::withMessages([
                    'kitchen' => 'وحدة المطبخ غير مفعلة. فعّلها قبل إرسال الطلب.',
                ]);
            }
            $data['payment_arrangement'] = 'pay_on_pickup';
            unset($data['payment_method_id'], $data['paid_amount'], $data['reference_number'],
                $data['payment_proof'], $data['payment_received_confirmed']);
        }

        if ($action === 'bill_print' && (
            $data['payment_arrangement'] === 'pending_verification'
            || (! empty($data['payment_method_id'])
                && PaymentMethod::query()->whereKey($data['payment_method_id'])
                    ->where('requires_verification', true)->exists())
        )) {
            throw ValidationException::withMessages([
                'payment_arrangement' => 'لا يمكن طباعة الفاتورة قبل التحقق من الدفع. استخدم «الفاتورة والدفع» لتسجيل الطلب بانتظار التحقق.',
            ]);
        }

        $data['location_id'] = $location->id;
        $data['payment_proof'] = $request->file(
            'payment_proof'
        );

        if (! in_array($data['payment_arrangement'], ['pay_on_pickup', 'on_account'], true)
            && ! empty($data['payment_method_id'])) {
            $method = PaymentMethod::query()->active()->find($data['payment_method_id']);
            if (! $method || ($method->locationPaymentMethods()->exists()
                && ! $method->locationPaymentMethods()
                    ->where('location_id', $location->id)
                    ->where('is_active', true)->exists())) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'طريقة الدفع غير مفعلة في هذا الفرع.',
                ]);
            }
        }

        if (! empty($data['customer_id'])) {
            $customerAllowed = Customer::query()
                ->availableAt($location->id)
                ->whereKey($data['customer_id'])
                ->exists();

            abort_unless(
                $customerAllowed,
                422,
                'العميل غير متاح في هذا الفرع.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Server-side POS menu protection
        |--------------------------------------------------------------------------
        */
        $requestedProductIds = collect(
            $data['items'] ?? []
        )
            ->pluck('product_id')
            ->map(
                fn ($id) => (int) $id
            )
            ->filter()
            ->unique()
            ->values();

        $allowedProductIds = Product::query()
            ->active()
            ->whereIn(
                'id',
                $requestedProductIds
            )
            ->whereHas(
                'restaurantMenuItems',
                fn ($menuItem) => $menuItem
                    ->where(
                        'location_id',
                        $location->id
                    )
                    ->where('is_active', true)
                    ->where('show_in_pos', true)
            )
            ->whereHas(
                'locationProducts',
                fn ($locationProducts) =>
                    $locationProducts
                        ->where(
                            'location_id',
                            $location->id
                        )
                        ->where(
                            'is_available',
                            true
                        )
            )
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            );

        $invalidProductIds =
            $requestedProductIds
                ->diff($allowedProductIds);

        if ($invalidProductIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' =>
                    'أحد أصناف الطلب غير متاح للبيع في منيو هذا الفرع. حدّث شاشة نقطة البيع وحاول مرة أخرى.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create order once
        |--------------------------------------------------------------------------
        |
        | The browser now uses fetch() for POS actions. Do not redirect that AJAX
        | request to orders.show, because fetch would follow the whole HTML redirect
        | before the cashier receives a response.
        |
        */
        $order = $this->restaurantOrders
            ->createPosOrder(
                $data,
                $request->user()
            );

        $order->loadMissing(['invoice', 'kitchenTickets']);

        $status =
            $order->status instanceof \BackedEnum
                ? $order->status->value
                : (string) $order->status;

        $message =
            $status === 'draft'
                ? 'تم إنشاء طلب المطعم وهو بانتظار تحقق الدفع.'
                : 'تم إنشاء طلب المطعم وتأكيده بنجاح.';

        /*
        |--------------------------------------------------------------------------
        | AJAX / POS response
        |--------------------------------------------------------------------------
        */
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,

                'message' => $message,

                'order_id' =>
                    (int) $order->id,

                'order_number' =>
                    (string)
                    $order->order_number,

                'status' => $status,

                'kitchen_dispatched' => $order->kitchenTickets->isNotEmpty(),

                'kitchen_print_url' => $order->kitchenTickets->isNotEmpty()
                    ? route('restaurant.pos.orders.kitchen-print', $order)
                    : null,

                'redirect_url' =>
                    $request->user()->can('orders.view')
                        ? route('orders.show', $order)
                        : route('restaurant.pos.index', ['order_created' => $order->order_number]),

                'invoice_print_url' =>
                    $order->invoice
                        ? route('restaurant.pos.orders.invoice-print', $order)
                        : null,
            ], 201);
        }

        /*
        |--------------------------------------------------------------------------
        | Normal HTML fallback
        |--------------------------------------------------------------------------
        */
        return redirect()
            ->route(
                'orders.show',
                $order
            )
            ->with(
                'success',
                $message
            );
    }
}
