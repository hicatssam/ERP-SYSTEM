<?php

namespace App\Http\Controllers\CustomerOrdering;

use App\Http\Controllers\Concerns\ResolvesCustomerMenuBranding;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerOrdering\StoreCustomerMenuOrderRequest;
use App\Models\Employee;
use App\Models\Location;
use App\Models\LocationPaymentMethod;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\ModuleService;
use App\Services\Restaurant\CustomerOrderingService;
use App\Services\Restaurant\CustomerOrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerMenuController extends Controller
{
    use ResolvesCustomerMenuBranding;

    public function __construct(
        private readonly CustomerOrderingService $ordering,
        private readonly CustomerOrderStatusService $orderStatus,
        private readonly ModuleService $modules
    ) {
    }

    public function show(Request $request, Location $location): View
    {
        $this->assertMenuAvailable($location);

        $tables = $this->tableOptions($location);

        return view('customer-menu.show', [
            'location' => $location,
            'menuItems' => $this->menuItemsFor($location),
            'categories' => $this->categoriesFor(),
            'banners' => $this->bannersFor($location),
            'tables' => $tables,
            'selectedTable' => $this->resolveSelectedTable($tables, (string) $request->query('table', '')),
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
            'requestToken' => (string) Str::uuid(),
            'serviceOptions' => $this->serviceOptions(),
            'team' => $this->teamMembers($location),
        ]);
    }

    public function products(Request $request, Location $location): View
    {
        $this->assertMenuAvailable($location);

        return view('customer-menu.products', [
            'location' => $location,
            'menuItems' => $this->menuItemsFor($location),
            'categories' => $this->categoriesFor(),
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
            'initialCategory' => (string) $request->query('category', 'all'),
            'initialSearch' => (string) $request->query('q', ''),
        ]);
    }

    public function productShow(Location $location, int $product): View
    {
        $this->assertMenuAvailable($location);

        $menuItems = $this->menuItemsFor($location);
        $item = $menuItems->firstWhere('product_id', $product);

        abort_unless($item !== null, 404);

        return view('customer-menu.product', [
            'location' => $location,
            'product' => $item,
            'menuItems' => $menuItems,
            'relatedItems' => $menuItems
                ->where('category_id', $item['category_id'])
                ->reject(fn (array $row) => $row['product_id'] === $item['product_id'])
                ->take(6)
                ->values(),
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
        ]);
    }

    public function favorites(Location $location): View
    {
        $this->assertMenuAvailable($location);

        return view('customer-menu.favorites', [
            'location' => $location,
            'menuItems' => $this->menuItemsFor($location),
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
        ]);
    }

    public function paymentOptions(Location $location): JsonResponse
    {
        $this->assertMenuAvailable($location);

        $methods = LocationPaymentMethod::query()
            ->with([
                'paymentMethod',
                'activeAccounts',
            ])
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->whereHas('paymentMethod', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (LocationPaymentMethod $row): string => sprintf(
                '%010d-%010d',
                (int) ($row->paymentMethod?->sort_order ?? 0),
                (int) ($row->paymentMethod?->id ?? 0)
            ))
            ->map(function (LocationPaymentMethod $locationMethod): array {
                $method = $locationMethod->paymentMethod;

                $accounts = $locationMethod->activeAccounts
                    ->map(fn ($account): array => [
                        'id' => (int) $account->id,
                        'name' => (string) $account->name,
                        'provider_name' => $account->provider_name,
                        'account_holder_name' => $account->account_holder_name,
                        'account_number' => $account->account_number,
                        'iban' => $account->iban,
                        'phone_number' => $account->phone_number,
                        'wallet_number' => $account->wallet_number,
                        'instructions' => $account->instructions,
                    ])
                    ->values();

                return [
                    'id' => (int) $method->id,
                    'location_payment_method_id' => (int) $locationMethod->id,
                    'name' => (string) ($method->name_ar ?: $method->name),
                    'code' => (string) $method->code,
                    'type' => (string) $method->type,
                    'logo' => $this->assetFromPath($method->logo ?: $method->logo_path),
                    'requires_verification' => (bool) $method->requires_verification,
                    'requires_reference' => (bool) $method->requires_reference,
                    'accounts' => $accounts->all(),
                ];
            })
            ->values();

        return response()
            ->json(['payment_methods' => $methods->all()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function tables(Location $location): JsonResponse
    {
        $this->assertMenuAvailable($location);

        return response()
            ->json([
                'tables' => $this->tableOptions($location)->values()->all(),
                'updated_at' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function queueStatus(Location $location): JsonResponse
    {
        $this->assertMenuAvailable($location);

        return response()
            ->json(array_merge(
                $this->etaSettings(),
                ['queue_count' => $this->activeQueueCount($location)]
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function myOrders(Location $location): View
    {
        $this->assertMenuAvailable($location);

        return view('customer-menu.my-orders', [
            'location' => $location,
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
        ]);
    }

    public function store(
        StoreCustomerMenuOrderRequest $request,
        Location $location
    ): JsonResponse {
        $this->assertMenuAvailable($location);

        $order = $this->ordering->create(
            $location,
            $request->validated()
        );

        return response()->json([
            'ok' => true,
            'message' => 'تم استلام طلبك بنجاح.',
            'order_number' => $order->order_number,
            'order_id' => (int) $order->id,
            'public_token' => (string) $order->public_token,
            'created_at' => $order->created_at?->toIso8601String(),
            'location' => [
                'id' => (int) $location->id,
                'name' => (string) $location->name,
                'code' => (string) $location->code,
            ],
            'track_url' => route(
                'customer-menu.track',
                ['token' => $order->public_token]
            ),
        ], 201);
    }

    public function track(string $token): View
    {
        $order = $this->publicOrder($token);

        return view('customer-menu.track', [
            'order' => $order,
            'branding' => $this->branding($order->location),
            'theme' => $this->theme(),
            'statusPayload' => $this->orderStatus->payload($order),
        ]);
    }

    public function status(string $token): JsonResponse
    {
        $order = $this->publicOrder($token);

        return response()
            ->json($this->orderStatus->payload($order))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function invoice(string $token): View
    {
        $order = $this->publicOrder($token);
        $invoice = $order->invoice()
            ->with(['items.product', 'location', 'customer'])
            ->firstOrFail();

        app(\App\Services\Invoices\InvoiceService::class)
            ->syncPaymentAmounts($invoice);

        $invoice->refresh()->load(['items.product', 'location', 'customer']);
        $statusPayload = $this->orderStatus->payload($order);

        return view('customer-menu.invoice', [
            'order' => $order,
            'invoice' => $invoice,
            'payment' => $statusPayload['payment'] ?? [],
            'branding' => $this->branding($order->location),
            'theme' => $this->theme(),
        ]);
    }

    private function assertMenuAvailable(Location $location): void
    {
        abort_unless(
            (bool) $location->is_active && $location->isBranch(),
            404
        );
    }

    private function publicOrder(string $token): Order
    {
        return Order::query()
            ->where('order_source', 'customer_menu')
            ->where('public_token', $token)
            ->with([
                'location',
                'customer',
                'items',
                'restaurantTable.area',
            ])
            ->firstOrFail();
    }

    private function teamMembers(Location $location): Collection
    {
        if (! (bool) SystemSetting::get('customer_menu_show_team', false)) {
            return collect();
        }

        $limit = max(1, min(12, (int) SystemSetting::get('customer_menu_team_limit', 6)));

        return Employee::query()
            ->where('employment_status', 'active')
            ->whereHas('locations', fn ($query) => $query->where('locations.id', (int) $location->id))
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'full_name', 'profile_image', 'job_title'])
            ->map(fn (Employee $employee): array => [
                'id' => (int) $employee->id,
                'name' => (string) $employee->full_name,
                'job_title' => trim((string) ($employee->job_title ?? '')),
                'image' => $this->assetFromPath((string) ($employee->profile_image ?? '')),
            ])
            ->values();
    }
}
