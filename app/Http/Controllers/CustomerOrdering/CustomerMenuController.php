<?php

namespace App\Http\Controllers\CustomerOrdering;

use App\Enums\RestaurantServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerOrdering\StoreCustomerMenuOrderRequest;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\LocationPaymentAccount;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use App\Models\SystemSetting;
use App\Services\ModuleService;
use App\Services\Restaurant\CustomerOrderingService;
use App\Services\Restaurant\CustomerOrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerMenuController extends Controller
{
    public function __construct(
        private readonly CustomerOrderingService $ordering,
        private readonly CustomerOrderStatusService $orderStatus,
        private readonly ModuleService $modules
    ) {
    }

    public function show(Request $request, Location $location): View
    {

    
        $this->assertMenuAvailable($location);

        $showUnavailable = (bool) SystemSetting::get(
            'customer_menu_show_unavailable',
            false
        );

        $categoryColumns = ['id', 'name', 'name_ar'];

        if (Schema::hasColumn('categories', 'icon_key')) {
            $categoryColumns[] = 'icon_key';
        }

        if (Schema::hasColumn('categories', 'icon_color')) {
            $categoryColumns[] = 'icon_color';
        }

        $menuQuery = RestaurantMenuItem::query()
            ->forQr((int) $location->id)
            ->whereHas('product', fn ($query) => $query->active())
            ->with([
                'product.category' => fn ($query) => $query
                    ->select($categoryColumns),
                'product.locationProducts' => fn ($query) => $query
                    ->where('location_id', $location->id),
            ])
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $showUnavailable) {
            $menuQuery->whereHas(
                'product.locationProducts',
                fn ($query) => $query
                    ->where('location_id', $location->id)
                    ->where('is_available', true)
            );
        }

        $menuItems = $menuQuery
            ->limit(250)
            ->get()
            ->map(function (RestaurantMenuItem $menuItem) use ($location): array {
                $product = $menuItem->product;
                $locationProduct = $product?->locationProducts->first();

                return [
                    'menu_item_id' => (int) $menuItem->id,
                    'product_id' => (int) $product->id,
                    'name' => $menuItem->displayName(),
                    'description' => trim(
                        (string) ($menuItem->effectiveDescription() ?? '')
                    ),
                    'image' => $this->assetFromPath(
                        $menuItem->effectiveImage()
                        ?: ($product->image ?? null)
                        ?: ($product->image_path ?? null)
                        ?: ($product->photo ?? null)
                        ?: ($product->photo_path ?? null)
                    ),
                    'category_id' => $product->category?->id,
                    'category' => $product->category?->name_ar
                        ?: $product->category?->name
                        ?: 'أخرى',
                    'category_icon' => $product->category?->icon_key
                        ?: $this->guessCategoryIcon(
                            $product->category?->name_ar
                            ?: $product->category?->name
                            ?: 'أخرى'
                        ),
                    'category_icon_color' => $product->category?->icon_color
                        ?: '#111111',
                    'sku' => $product->sku,
                    'price' => (float) $product->getEffectivePriceForLocation(
                        (int) $location->id
                    ),
                    'available' => (bool) (
                        $locationProduct?->is_available ?? false
                    ),
                    'delivery_available' => (bool) $menuItem->show_in_delivery,
                ];
            })
            ->values();

        /*
         * Categories are loaded directly from the catalog.
         * This makes the QR menu follow the Categories screen in the ERP
         * instead of deriving categories from the currently rendered products.
         */
        $categoryColumns = ['id', 'name', 'name_ar', 'sort_order'];

        foreach (['slug', 'image', 'icon_key', 'icon_color'] as $column) {
            if (Schema::hasColumn('categories', $column)) {
                $categoryColumns[] = $column;
            }
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByRaw("COALESCE(NULLIF(name_ar, ''), name)")
            ->get($categoryColumns)
            ->map(function (Category $category): array {
                $name = filled($category->name_ar)
                    ? $category->name_ar
                    : $category->name;

                return [
                    'id' => $category->id,
                    'name' => $name,
                    'name_ar' => $category->name_ar,
                    'name_en' => $category->name,
                    'slug' => $category->slug ?? null,
                    'image' => $this->assetFromPath($category->image ?? null),
                    'icon_key' => $category->icon_key
                        ?? $this->guessCategoryIcon($name),
                    'icon_color' => $category->icon_color
                        ?? '#C98516',
                ];
            })
            ->values();

        $tables = $this->tableOptions($location);

        $selectedTable = $this->resolveSelectedTable(
            $tables,
            (string) $request->query('table', '')
        );

        return view('customer-menu.show', [
            'location' => $location,
            'menuItems' => $menuItems,
            'categories' => $categories,
            'tables' => $tables,
            'selectedTable' => $selectedTable,
            'branding' => $this->branding($location),
            'theme' => $this->theme(),
            'requestToken' => (string) Str::uuid(),
            'serviceOptions' => $this->serviceOptions(),
            'team' => $this->teamMembers($location),
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

    public function myOrders(Location $location): View
    {
        $this->assertMenuAvailable($location);

        return view('customer-menu.my-orders', [
            'location' => $location,
            'branding' => $this->branding(),
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

    private function tableOptions(Location $location): Collection
    {
        return RestaurantTable::query()
            ->forLocation((int) $location->id)
            ->active()
            ->with([
                'area:id,name',
                'activeSession',
            ])
            ->orderBy('area_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (RestaurantTable $table): array {
                $occupied = $table->activeSession !== null;

                return [
                    'id' => (int) $table->id,
                    'code' => (string) ($table->code ?? ''),
                    'name' => (string) $table->displayName(),
                    'area' => (string) ($table->area?->name ?? ''),
                    'capacity' => (int) ($table->capacity ?? 0),
                    'status' => $occupied ? 'occupied' : 'available',
                    'available' => ! $occupied,
                    'status_label' => $occupied ? 'مشغولة' : 'متاحة',
                    'occupied_since' => $table->activeSession?->opened_at?->toIso8601String(),
                ];
            })
            ->values();
    }

    private function resolveSelectedTable(
        Collection $tables,
        string $table
    ): ?array {
        $table = trim($table);

        if ($table === '') {
            return null;
        }

        $selected = $tables->first(function (array $candidate) use ($table): bool {
            return (string) $candidate['id'] === $table
                || strcasecmp((string) $candidate['code'], $table) === 0;
        });

        if (! is_array($selected) || ! ($selected['available'] ?? false)) {
            return null;
        }

        return $selected;
    }

    private function serviceOptions(): array
    {
        return collect([
            [
                'value' => RestaurantServiceType::DineIn->value,
                'label' => RestaurantServiceType::DineIn->label(),
                'enabled' => (bool) SystemSetting::get(
                    'customer_menu_allow_dine_in',
                    true
                ),
            ],
            [
                'value' => RestaurantServiceType::Takeaway->value,
                'label' => RestaurantServiceType::Takeaway->label(),
                'enabled' => (bool) SystemSetting::get(
                    'customer_menu_allow_takeaway',
                    true
                ),
            ],
            [
                'value' => RestaurantServiceType::Delivery->value,
                'label' => RestaurantServiceType::Delivery->label(),
                'enabled' => (bool) SystemSetting::get(
                    'customer_menu_allow_delivery',
                    false
                ),
            ],
        ])
            ->where('enabled', true)
            ->values()
            ->all();
    }

    private function teamMembers(Location $location): Collection
    {
        if (! (bool) SystemSetting::get('customer_menu_show_team', false)) {
            return collect();
        }

        $limit = max(
            1,
            min(
                12,
                (int) SystemSetting::get('customer_menu_team_limit', 6)
            )
        );

        return Employee::query()
            ->where('employment_status', 'active')
            ->whereHas(
                'locations',
                fn ($query) => $query->where(
                    'locations.id',
                    (int) $location->id
                )
            )
            ->orderBy('full_name')
            ->limit($limit)
            ->get([
                'id',
                'full_name',
                'profile_image',
                'job_title',
            ])
            ->map(fn (Employee $employee): array => [
                'id' => (int) $employee->id,
                'name' => (string) $employee->full_name,
                'job_title' => trim((string) ($employee->job_title ?? '')),
                'image' => $this->assetFromPath(
                    (string) ($employee->profile_image ?? '')
                ),
            ])
            ->values();
    }

    private function branding(?Location $location = null): array
    {
        $name = (string) SystemSetting::get('system_name', 'حلويات دهب');
        $nameEn = (string) SystemSetting::get('system_name_en', 'Dahab Sweets');
        $tagline = (string) SystemSetting::get('brand_tagline_ar', '');
        $taglineEn = (string) SystemSetting::get('brand_tagline_en', '');

        $logo = SystemSetting::assetUrl('brand_logo');
        $logoSmall = SystemSetting::assetUrl('brand_logo_small');
        $favicon = SystemSetting::assetUrl('brand_favicon');

        // Safe fallbacks: if only one logo is configured, use it in both places.
        $logo = $logo ?: $logoSmall;
        $logoSmall = $logoSmall ?: $logo;

        return [
            'name' => $name,
            'name_en' => $nameEn,
            'tagline' => $tagline,
            'tagline_en' => $taglineEn,

            // Required by resources/views/customer-menu/show.blade.php
            'logo' => $logo,
            'logo_small' => $logoSmall,
            'favicon' => $favicon,

            'branch_name' => $location?->name,
            'branch_phone' => $location?->phone,
            'branch_address' => $location?->address,
        ];
    }

    private function theme(): array
    {
        /*
         * IMPORTANT:
         * These keys match Admin\SettingsController exactly.
         * Snake_case keys are also returned because the clean customer-menu
         * Blade uses them directly.
         */
        $primary = (string) SystemSetting::get('customer_menu_primary', '#704C34');
        $accent = (string) SystemSetting::get('customer_menu_accent', '#D79A55');
        $background = (string) SystemSetting::get('customer_menu_background', '#F7F3EE');
        $surface = (string) SystemSetting::get('customer_menu_surface', '#FFFFFF');
        $text = (string) SystemSetting::get('customer_menu_text', '#241D18');
        $muted = (string) SystemSetting::get('customer_menu_muted', '#7D746C');

        $radius = max(
            0,
            min(40, (int) SystemSetting::get('customer_menu_radius', 18))
        );

        $columns = max(
            2,
            min(5, (int) SystemSetting::get('customer_menu_columns', 3))
        );

        $heroHeight = max(
            220,
            min(720, (int) SystemSetting::get('customer_menu_hero_height', 430))
        );

        $showHero = (bool) SystemSetting::get(
            'customer_menu_show_hero',
            true
        );

        $cover = SystemSetting::assetUrl('customer_menu_cover');

        return [
            // Keys used by the clean Blade.
            'primary' => $primary,
            'accent' => $accent,
            'background' => $background,
            'surface' => $surface,
            'text' => $text,
            'muted' => $muted,
            'radius' => $radius,
            'columns' => $columns,
            'hero_height' => $heroHeight,
            'show_hero' => $showHero,
            'cover' => $cover,

            // Compatibility aliases for older customer-menu views.
            'heroHeight' => $heroHeight,
            'showHero' => $showHero,
        ];
    }

    private function guessCategoryIcon(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));

        return match (true) {
            str_contains($text, 'برغر'), str_contains($text, 'برجر'), str_contains($text, 'burger') => 'burger',
            str_contains($text, 'بيتزا'), str_contains($text, 'pizza') => 'pizza',
            str_contains($text, 'قهوة'), str_contains($text, 'coffee'), str_contains($text, 'كافيه') => 'coffee',
            str_contains($text, 'كيك'), str_contains($text, 'جاتوه'), str_contains($text, 'cake') => 'cake',
            str_contains($text, 'كرواسون'), str_contains($text, 'مخبوز'), str_contains($text, 'croissant') => 'croissant',
            str_contains($text, 'دونات'), str_contains($text, 'donut') => 'donut',
            str_contains($text, 'ايس كريم'), str_contains($text, 'آيس كريم'), str_contains($text, 'ice') => 'icecream',
            str_contains($text, 'عصير'), str_contains($text, 'juice') => 'juice',
            str_contains($text, 'مشروب'), str_contains($text, 'drink') => 'drink',
            str_contains($text, 'بطاط'), str_contains($text, 'fries') => 'fries',
            str_contains($text, 'دجاج'), str_contains($text, 'chicken') => 'chicken',
            str_contains($text, 'سلط'), str_contains($text, 'salad') => 'salad',
            str_contains($text, 'ساند'), str_contains($text, 'sandwich') => 'sandwich',
            str_contains($text, 'شوكولات'), str_contains($text, 'chocolate') => 'chocolate',
            str_contains($text, 'شاي'), str_contains($text, 'tea') => 'tea',
            str_contains($text, 'فطور'), str_contains($text, 'breakfast') => 'breakfast',
            str_contains($text, 'هدية'), str_contains($text, 'هدايا'), str_contains($text, 'gift') => 'gift',
            str_contains($text, 'حلويات'), str_contains($text, 'حلو'), str_contains($text, 'dessert') => 'dessert',
            default => 'sparkles',
        };
    }

    private function assetFromPath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '//')
            || str_starts_with($path, 'data:')
        ) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        foreach (['storage/app/public/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }

        if (file_exists(public_path($normalized))) {
            return asset($normalized);
        }

        if (file_exists(public_path('storage/' . $normalized))) {
            return asset('storage/' . $normalized);
        }

        return asset('storage/' . $normalized);
    }
}