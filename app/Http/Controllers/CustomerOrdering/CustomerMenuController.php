<?php

namespace App\Http\Controllers\CustomerOrdering;

use App\Http\Controllers\Concerns\ResolvesCustomerMenuBranding;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerOrdering\StoreCustomerMenuOrderRequest;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\LocationPaymentAccount;
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

    /**
     * The main scroll-story menu page: hero, categories, popular combos,
     * promo strip. Cart / checkout / product details / favorites now link
     * out to their own dedicated pages below instead of living only inside
     * on-page overlays.
     */
    public function show(Request $request, Location $location): View
    {
        $this->assertMenuAvailable($location);

        $tables = $this->tableOptions($location);
        $menuItems = $this->menuItemsFor($location);
        $theme = $this->theme();

        // Rank the reference design's "most ordered" cards from real sales
        // at this branch. New catalogs gracefully fall back to menu sort order.
        $popularProductIds = OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($query) => $query
                ->where('location_id', (int) $location->id)
                ->whereNotIn('status', ['draft', 'cancelled']))
            ->select('product_id')
            ->selectRaw('SUM(quantity) as units_sold')
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->limit((int) ($theme['featured_limit'] ?? 4))
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        return view('customer-menu.show', [
            'location' => $location,
            'menuItems' => $menuItems,
            'popularProductIds' => $popularProductIds,
            'categories' => $this->categoriesFor(),
            'banners' => $this->bannersFor($location),
            'tables' => $tables,
            'selectedTable' => $this->resolveSelectedTable($tables, (string) $request->query('table', '')),
            'branding' => $this->branding($location),
            'theme' => $theme,
            'requestToken' => (string) Str::uuid(),
            'serviceOptions' => $this->serviceOptions(),
            'team' => $this->teamMembers($location),
        ]);
    }

    /**
     * Full, searchable/filterable product catalog for this branch.
     */
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

    /**
     * A single product's own shareable page (used by "product details"
     * links and anything shared outside the app).
     */
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

    /**
     * Favorites live in the browser's storage (no login on the public
     * menu), so this page just needs the branded shell plus the full
     * product catalog to resolve favorited IDs against.
     */
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

        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->whereHas('locationPaymentMethods', function ($query) use ($location): void {
                $query
                    ->where('location_id', $location->id)
                    ->where('is_active', true);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (PaymentMethod $method) use ($location): array {
                $accounts = LocationPaymentAccount::query()
                    ->where('location_id', $location->id)
                    ->where('payment_method_id', $method->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (LocationPaymentAccount $account): array => [
                        'id' => (int) $account->id,
                        'name' => (string) $account->name,
                        'provider_name' => $account->provider_name,
                        'account_holder_name' => $account->account_holder_name,
                        'account_number' => $account->account_number,
                        'iban' => $account->iban,
                        'phone_number' => $account->phone_number,
                        'instructions' => $account->instructions,
                    ])
                    ->values();

                return [
                    'id' => (int) $method->id,
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

    /**
     * How busy the kitchen is right now, so the checkout page can show a
     * realistic "estimated prep time" before the order is even placed.
     */
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

        $status = $this->orderStatus->payload($order);

        return response()->json([
            'ok' => true,
            'message' => 'تم استلام طلبك بنجاح.',
            'order_number' => $order->order_number,
            'order_id' => (int) $order->id,
            'public_token' => (string) $order->public_token,
            'created_at' => $order->created_at?->toIso8601String(),
            'status' => $status['status'],
            'state' => $status['state'],
            'status_label' => $status['label'],
            'location' => [
                'id' => (int) $location->id,
                'name' => (string) $location->name,
                'code' => (string) $location->code,
            ],
            'track_url' => route(
                'customer-menu.track',
                ['token' => $order->public_token]
            ),
            'status_url' => route(
                'customer-menu.status',
                ['token' => $order->public_token]
            ),
        ], 201);
    }

    public function track(string $token): View
    {
        $order = $this->publicOrder($token);

        return view('customer-menu.track', [
            'order' => $order,
            'branding' => $this->branding(),
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

    private function assertMenuAvailable(Location $location): void
    {
        /*
         * Keep the public menu reachable for every active branch.
         *
         * CustomerOrderingService still validates customer_menu_enabled and
         * the enabled service type before an order is actually created, so
         * removing the module/settings 404 here does not bypass order rules.
         */
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
