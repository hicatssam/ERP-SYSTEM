<?php

namespace App\Http\Controllers\Restaurant;

use App\Enums\RestaurantServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\StoreCustomerMenuOrderRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use App\Models\SalesChannel;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerMenuController extends Controller
{
    public function show(Location $location): View
    {
        abort_unless($location->is_active, 404);

        $records = RestaurantMenuItem::query()->forQr($location->id)
            ->with(['product.category', 'product.locationProducts' => fn ($q) => $q->where('location_id', $location->id)])
            ->orderBy('sort_order')->orderBy('id')->get();

        $menuItems = $records->map(function (RestaurantMenuItem $record) use ($location): array {
            $product = $record->product;
            $branch = $product?->locationProducts->first();
            return [
                'product_id' => $product?->id,
                'name' => $record->displayName(),
                'description' => $record->effectiveDescription(),
                'image' => $this->assetUrl($record->effectiveImage()),
                'price' => (float) ($branch?->local_selling_price ?? $product?->base_selling_price ?? 0),
                'category_id' => $product?->category_id,
                'category' => $product?->category?->name_ar ?: $product?->category?->name,
                'available' => (bool) ($product?->is_active && ($branch?->is_available ?? true)),
                'delivery_available' => (bool) $record->show_in_delivery,
            ];
        })->filter(fn (array $item) => $item['product_id'])->values();

        $categories = $menuItems->groupBy('category_id')->map(fn ($items) => [
            'id' => $items->first()['category_id'],
            'name' => $items->first()['category'] ?: 'أخرى',
            'count' => $items->count(),
        ])->values();

        $paymentMethods = PaymentMethod::query()->active()
            ->whereHas('locationPaymentMethods', fn ($q) => $q->where('location_id', $location->id)->where('is_active', true))
            ->get()->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name_ar ?: $method->name,
                'type' => $method->type,
                'logo' => $this->assetUrl($method->logo_path ?: $method->logo),
                'requires_verification' => $method->requires_verification,
                'requires_reference' => $method->requires_reference,
                'description' => $method->description,
            ])->values();

        return view('customer-menu.show', [
            'location' => $location,
            'menuItems' => $menuItems,
            'categories' => $categories,
            'paymentMethods' => $paymentMethods,
            'tables' => $this->availableTables($location),
            'requestToken' => (string) Str::uuid(),
            'branding' => $this->branding(),
            'theme' => $this->theme(),
        ]);
    }

    public function tables(Location $location): JsonResponse
    {
        return response()->json(['tables' => $this->availableTables($location)]);
    }

    public function store(StoreCustomerMenuOrderRequest $request, Location $location, RestaurantOrderService $orders): JsonResponse
    {
        $existing = Order::query()->where('public_request_token', $request->string('request_token'))->first();
        if ($existing) {
            return response()->json($this->orderResponse($existing));
        }

        $user = User::query()->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('name', 'Admin'))->first()
            ?? User::query()->where('is_active', true)->firstOrFail();
        $channel = SalesChannel::query()->active()->where('type', 'direct')->orderBy('sort_order')->first()
            ?? SalesChannel::query()->active()->orderBy('sort_order')->firstOrFail();

        $customer = Customer::query()->where('phone', $request->string('phone'))->first();
        if (! $customer) {
            $customer = Customer::query()->create([
                'location_id' => $location->id, 'customer_type' => Customer::TYPE_INDIVIDUAL,
                'scope' => Customer::SCOPE_BRANCH, 'name' => $request->string('name'),
                'phone' => $request->string('phone'), 'address' => $request->input('address'),
                'allow_credit' => false,
            ]);
        }

        $arrangement = (string) $request->input('payment_arrangement');
        $method = $request->filled('payment_method_id')
            ? PaymentMethod::query()->find($request->integer('payment_method_id')) : null;
        if ($method?->requires_verification) {
            $arrangement = 'pending_verification';
        }

        $payload = $request->validated();
        $payload += [
            'location_id' => $location->id, 'customer_id' => $customer->id,
            'sales_channel_id' => $channel->id, 'discount_type' => 'none', 'discount_value' => 0,
        ];
        $payload['payment_arrangement'] = $arrangement;
        $payload['paid_amount'] = 0;
        $payload['payment_proof'] = $request->file('payment_proof');

        $order = DB::transaction(function () use ($orders, $payload, $user, $request, $location): Order {
            $order = $orders->createPosOrder($payload, $user);
            $order->update([
                'public_token' => (string) Str::uuid(),
                'public_request_token' => (string) $request->input('request_token'),
                'guest_name' => (string) $request->input('name'),
                'guest_phone' => (string) $request->input('phone'),
                'delivery_address' => $request->input('address'),
                'order_source' => 'customer_menu',
            ]);
            return $order->fresh(['items', 'location', 'payments.paymentMethod', 'kitchenTickets']);
        });

        return response()->json($this->orderResponse($order), 201);
    }

    public function track(string $token): View
    {
        $order = $this->publicOrder($token);
        return view('customer-menu.track', ['order' => $order, 'branding' => $this->branding(), 'theme' => $this->theme()]);
    }

    public function status(string $token): JsonResponse
    {
        return response()->json($this->orderResponse($this->publicOrder($token)));
    }

    public function myOrders(Location $location): View
    {
        return view('customer-menu.my-orders', ['location' => $location, 'branding' => $this->branding(), 'theme' => $this->theme()]);
    }

    private function publicOrder(string $token): Order
    {
        return Order::query()->where('public_token', $token)
            ->with(['items', 'location', 'payments.paymentMethod', 'kitchenTickets'])->firstOrFail();
    }

    private function availableTables(Location $location): array
    {
        return RestaurantTable::query()->forLocation($location->id)->active()->with(['area', 'activeSession'])
            ->orderBy('sort_order')->get()->map(fn (RestaurantTable $table) => [
                'id' => $table->id, 'name' => $table->displayName(), 'area' => $table->area?->name,
                'capacity' => $table->capacity, 'available' => ! $table->isOccupied(),
            ])->values()->all();
    }

    private function orderResponse(Order $order): array
    {
        $order->loadMissing(['location', 'items', 'payments.paymentMethod', 'kitchenTickets']);
        return [
            'public_token' => $order->public_token, 'order_number' => $order->order_number,
            'status' => $order->statusValue(), 'payment_status' => $order->payment_status?->value ?? $order->payment_status,
            'total' => (float) $order->total_amount, 'created_at' => $order->created_at?->toIso8601String(),
            'location' => ['code' => $order->location?->code, 'name' => $order->location?->name],
            'track_url' => route('customer-menu.track', ['token' => $order->public_token]),
            'items' => $order->items->map->only(['product_name', 'quantity', 'line_total'])->values(),
            'kitchen' => $order->kitchenTickets->pluck('status')->map(fn ($status) => $status instanceof \BackedEnum ? $status->value : $status)->values(),
        ];
    }

    private function branding(): array
    {
        return [
            'name' => SystemSetting::get('system_name', 'Dahab'),
            'tagline' => SystemSetting::get('brand_tagline_ar', ''),
            'logo' => SystemSetting::assetUrl('brand_logo'),
            'cover' => SystemSetting::assetUrl('customer_menu_cover'),
            'phone' => SystemSetting::get('business_phone', ''),
        ];
    }

    private function theme(): array
    {
        return [
            'primary' => SystemSetting::get('customer_menu_primary', '#8f112f'),
            'accent' => SystemSetting::get('customer_menu_accent', '#e9a91b'),
            'background' => SystemSetting::get('customer_menu_background', '#f7f4ef'),
            'surface' => SystemSetting::get('customer_menu_surface', '#ffffff'),
            'text' => SystemSetting::get('customer_menu_text', '#191919'),
            'muted' => SystemSetting::get('customer_menu_muted', '#707070'),
            'radius' => (int) SystemSetting::get('customer_menu_radius', 18),
            'cardColumns' => (int) SystemSetting::get('customer_menu_columns', 3),
            'heroHeight' => (int) SystemSetting::get('customer_menu_hero_height', 360),
            'showHero' => (bool) SystemSetting::get('customer_menu_show_hero', true),
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
        $path = preg_replace('#^(?:public/)?storage/#', '', ltrim(str_replace('\\', '/', $path), '/'));
        return Storage::disk('public')->exists($path) ? Storage::url($path) : asset(ltrim($path, '/'));
    }
}
