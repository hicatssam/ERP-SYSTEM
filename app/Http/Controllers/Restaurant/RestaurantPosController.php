<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\PaymentArrangement;
use App\Enums\RestaurantServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreRestaurantPosOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\SalesChannel;
use App\Services\Restaurant\RestaurantContextService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RestaurantPosController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
        private readonly RestaurantOrderService $restaurantOrders
    ) {
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

        $products = Product::query()
            ->active()
            ->whereHas(
                'restaurantMenuItems',
                fn ($menuItem) => $menuItem
                    ->where('location_id', $location->id)
                    ->where('is_active', true)
                    ->where('show_in_pos', true)
            )
            ->whereHas(
                'locationProducts',
                fn ($query) => $query
                    ->where('location_id', $location->id)
                    ->where('is_available', true)
            )
            ->with([
                'category:id,name,name_ar',
                'restaurantMenuItems' => fn ($menuItem) => $menuItem
                    ->where('location_id', $location->id)
                    ->where('is_active', true)
                    ->where('show_in_pos', true),
                'locationProducts' => fn ($query) => $query
                    ->where('location_id', $location->id),
                'activeVariants.size',
                'activeVariants.attributeValues.attribute',
                'modifierGroupLinks' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'modifierGroupLinks.group.modifiers' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->availableAt($location->id)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        // Legacy methods without location mappings remain visible. Once a method
        // has branch mappings, POS shows it only in branches where it is active.
        $paymentMethods = PaymentMethod::query()
            ->active()
            ->where(function ($query) use ($location): void {
                $query
                    ->whereDoesntHave('locationPaymentMethods')
                    ->orWhereHas('locationPaymentMethods', function ($locationMethod) use ($location): void {
                        $locationMethod
                            ->where('location_id', $location->id)
                            ->where('is_active', true);
                    });
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

        return view('restaurant.pos.index', [
            'location' => $location,
            'locations' => $locations,
            'products' => $products,
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
            'salesChannels' => $salesChannels,
            'tables' => $tables,
            'recentOrders' => $recentOrders,
            'serviceTypes' => RestaurantServiceType::cases(),
            'paymentArrangements' => PaymentArrangement::cases(),
        ]);
    }

    public function store(StoreRestaurantPosOrderRequest $request)
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $data = $request->validated();
        $data['location_id'] = $location->id;
        $data['payment_proof'] = $request->file('payment_proof');

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

        $requestedProductIds = collect($data['items'] ?? [])
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $allowedProductIds = Product::query()
            ->active()
            ->whereIn('id', $requestedProductIds)
            ->whereHas(
                'restaurantMenuItems',
                fn ($menuItem) => $menuItem
                    ->where('location_id', $location->id)
                    ->where('is_active', true)
                    ->where('show_in_pos', true)
            )
            ->whereHas(
                'locationProducts',
                fn ($locationProducts) => $locationProducts
                    ->where('location_id', $location->id)
                    ->where('is_available', true)
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $invalidProductIds = $requestedProductIds->diff($allowedProductIds);

        if ($invalidProductIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'أحد أصناف الطلب غير متاح للبيع في منيو هذا الفرع. حدّث شاشة نقطة البيع وحاول مرة أخرى.',
            ]);
        }

        $order = $this->restaurantOrders->createPosOrder(
            $data,
            $request->user()
        );

        $order->loadMissing('invoice');

        $status = $order->status instanceof \BackedEnum
            ? $order->status->value
            : (string) $order->status;

        $message = $status === 'draft'
            ? 'تم إنشاء طلب المطعم وهو بانتظار تحقق الدفع.'
            : 'تم إنشاء طلب المطعم وتأكيده بنجاح.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'order_id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'status' => $status,
                'redirect_url' => route('orders.show', $order),
                'invoice_print_url' => $order->invoice
                    ? route('invoices.print', $order->invoice)
                    : null,
            ], 201);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', $message);
    }
}
