<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\RestaurantServiceType;
use App\Models\Category;
use App\Models\Location;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantTable;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for the customer-menu branding/theme tokens and
 * the shared menu/category queries.
 *
 * Every public customer-menu controller (the menu page, products page,
 * product page, cart page, checkout page, favorites page, my-orders,
 * track...) must resolve $branding/$theme through this trait so a change
 * in the admin "Customer menu branding" settings instantly reflects on
 * every page instead of only the ones a developer remembered to update.
 */
trait ResolvesCustomerMenuBranding
{
    /**
     * Branding identity: name, tagline, logo, favicon, branch info.
     */
    protected function branding(?Location $location = null): array
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

            'logo' => $logo,
            'logo_small' => $logoSmall,
            'favicon' => $favicon,

            'branch_name' => $location?->name,
            'branch_phone' => $location?->phone,
            'branch_address' => $location?->address,
        ];
    }

    /**
     * Visual theme tokens (colors, radius, grid columns, hero...).
     *
     * Keys match Admin\SettingsController exactly, and are the ones every
     * customer-menu Blade view reads through `--menu-*` CSS variables.
     */
    protected function theme(): array
    {
        $primary = (string) SystemSetting::get('customer_menu_primary', '#704C34');
        $accent = (string) SystemSetting::get('customer_menu_accent', '#D79A55');
        $background = (string) SystemSetting::get('customer_menu_background', '#F7F3EE');
        $surface = (string) SystemSetting::get('customer_menu_surface', '#FFFFFF');
        $text = (string) SystemSetting::get('customer_menu_text', '#241D18');
        $muted = (string) SystemSetting::get('customer_menu_muted', '#7D746C');

        $radius = max(0, min(40, (int) SystemSetting::get('customer_menu_radius', 18)));
        $columns = max(2, min(5, (int) SystemSetting::get('customer_menu_columns', 3)));

        $heroHeight = max(220, min(720, (int) SystemSetting::get('customer_menu_hero_height', 430)));
        $showHero = (bool) SystemSetting::get('customer_menu_show_hero', true);

        $cover = SystemSetting::assetUrl('customer_menu_cover');

        return [
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

    /**
     * Every product available at this location, in the shape every
     * customer-menu page expects (grid cards, product page, cart rows...).
     */
    protected function menuItemsFor(Location $location): Collection
    {
        $showUnavailable = (bool) SystemSetting::get('customer_menu_show_unavailable', false);

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
                'product.category' => fn ($query) => $query->select($categoryColumns),
                'product.locationProducts' => fn ($query) => $query
                    ->where('location_id', $location->id),
                'product.activeVariants' => fn ($query) => $query
                    ->with(['size:id,name,name_ar', 'color:id,name,name_ar'])
                    ->orderBy('sort_order'),
                'product.modifierGroupLinks' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('product_variant_id')
                    ->with(['group' => fn ($q) => $q->where('is_active', true)->with([
                        'modifiers' => fn ($mq) => $mq->where('is_active', true),
                    ])]),
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

        return $menuQuery
            ->limit(250)
            ->get()
            ->map(function (RestaurantMenuItem $menuItem) use ($location): array {
                $product = $menuItem->product;
                $locationProduct = $product?->locationProducts->first();

                $variants = $product->isVariantProduct()
                    ? $product->activeVariants
                        ->map(fn ($variant): array => [
                            'id' => (int) $variant->id,
                            'name' => $variant->displayName(),
                            'price' => $variant->selling_price !== null
                                ? (float) $variant->selling_price
                                : (float) $product->getEffectivePriceForLocation((int) $location->id),
                            'is_default' => (bool) $variant->is_default,
                            'image' => $this->assetFromPath($variant->image),
                        ])
                        ->values()
                        ->all()
                    : [];

                // Variant photos live in a separate admin screen/folder
                // (product-variants/...) from the base product photo
                // (products/...). If nobody uploaded a photo on the base
                // product itself, fall back to the default variant's photo
                // (or the first variant that has one) so the card still
                // shows something instead of the letter placeholder.
                $fallbackVariantImage = collect($variants)
                    ->sortByDesc('is_default')
                    ->pluck('image')
                    ->filter()
                    ->first();

                $modifierGroups = collect($product->modifierGroupLinks ?? [])
                    ->filter(fn ($link) => $link->group !== null)
                    ->unique('modifier_group_id')
                    ->map(function ($link): array {
                        $group = $link->group;
                        $required = $link->is_required_override ?? (bool) $group->is_required;
                        $min = $link->min_selections_override ?? (int) $group->min_selections;
                        $max = $link->max_selections_override ?? $group->max_selections;

                        if ($required && $min < 1) {
                            $min = 1;
                        }

                        if ($group->selection_type === \App\Enums\ModifierSelectionType::Single) {
                            $max = 1;
                        }

                        return [
                            'id' => (int) $group->id,
                            'name' => $group->name_ar ?: $group->name,
                            'selection_type' => $group->selection_type->value,
                            'required' => $required,
                            'min' => $min,
                            'max' => $max,
                            'modifiers' => $group->modifiers
                                ->map(fn ($modifier): array => [
                                    'id' => (int) $modifier->id,
                                    'name' => $modifier->name_ar ?: $modifier->name,
                                    'price_delta' => (float) $modifier->price_delta,
                                    'allow_quantity' => (bool) $modifier->allow_quantity,
                                    'max_quantity' => max(1, (int) $modifier->max_quantity),
                                    'is_default' => (bool) $modifier->is_default,
                                ])
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'menu_item_id' => (int) $menuItem->id,
                    'product_id' => (int) $product->id,
                    'name' => $menuItem->displayName(),
                    'description' => trim((string) ($menuItem->effectiveDescription() ?? '')),
                    'image' => $this->assetFromPath(
                        // Prefer the photo uploaded on the product itself
                        // (the Products screen you use day-to-day). Only
                        // fall back to the QR-menu item's own image (a
                        // separate, rarely-touched upload in the Restaurant
                        // Menu screen) if the product has no photo at all —
                        // otherwise a placeholder set there once would
                        // permanently hide every real photo you upload later.
                        ($product->image ?? null)
                        ?: $menuItem->image
                    ) ?: $fallbackVariantImage,
                    'category_id' => $product->category?->id,
                    'category' => $product->category?->name_ar
                        ?: $product->category?->name
                        ?: 'أخرى',
                    'category_icon' => $product->category?->icon_key
                        ?: $this->guessCategoryIcon(
                            $product->category?->name_ar ?: $product->category?->name ?: 'أخرى'
                        ),
                    'category_icon_color' => $product->category?->icon_color ?: '#111111',
                    'sku' => $product->sku,
                    'price' => (float) $product->getEffectivePriceForLocation((int) $location->id),
                    'available' => (bool) ($locationProduct?->is_available ?? false),
                    'delivery_available' => (bool) $menuItem->show_in_delivery,
                    'is_variant_product' => (bool) $product->isVariantProduct(),
                    'variants' => $variants,
                    'modifier_groups' => $modifierGroups,
                    'requires_choices' => ((bool) $product->isVariantProduct() && count($variants) > 0)
                        || collect($modifierGroups)->contains('required', true),
                ];
            })
            ->values();
    }

    /**
     * Active catalog categories, following the Categories screen in the ERP
     * rather than being derived from whatever happens to be on the menu.
     */
    protected function categoriesFor(): Collection
    {
        $categoryColumns = ['id', 'name', 'name_ar', 'sort_order'];

        foreach (['slug', 'image', 'icon_key', 'icon_color'] as $column) {
            if (Schema::hasColumn('categories', $column)) {
                $categoryColumns[] = $column;
            }
        }

        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByRaw("COALESCE(NULLIF(name_ar, ''), name)")
            ->get($categoryColumns)
            ->map(function (Category $category): array {
                $name = filled($category->name_ar) ? $category->name_ar : $category->name;

                return [
                    'id' => $category->id,
                    'name' => $name,
                    'name_ar' => $category->name_ar,
                    'name_en' => $category->name,
                    'slug' => $category->slug ?? null,
                    'image' => $this->assetFromPath($category->image ?? null),
                    'icon_key' => $category->icon_key ?? $this->guessCategoryIcon($name),
                    'icon_color' => $category->icon_color ?? '#C98516',
                ];
            })
            ->values();
    }

    /**
     * Active tables for the "dine-in" flow — shared by the menu page,
     * checkout page, and the /tables JSON endpoint.
     */
    protected function tableOptions(Location $location): Collection
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

    protected function resolveSelectedTable(Collection $tables, string $table): ?array
    {
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

    /**
     * Shared by the menu page, cart, and checkout so every page offers the
     * exact same dine-in/takeaway/delivery choices.
     */
    protected function serviceOptions(): array
    {
        return collect([
            [
                'value' => RestaurantServiceType::DineIn->value,
                'label' => RestaurantServiceType::DineIn->label(),
                'enabled' => (bool) SystemSetting::get('customer_menu_allow_dine_in', true),
            ],
            [
                'value' => RestaurantServiceType::Takeaway->value,
                'label' => RestaurantServiceType::Takeaway->label(),
                'enabled' => (bool) SystemSetting::get('customer_menu_allow_takeaway', true),
            ],
            [
                'value' => RestaurantServiceType::Delivery->value,
                'label' => RestaurantServiceType::Delivery->label(),
                'enabled' => (bool) SystemSetting::get('customer_menu_allow_delivery', false),
            ],
        ])
            ->where('enabled', true)
            ->values()
            ->all();
    }

    protected function guessCategoryIcon(?string $text): string
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

    protected function assetFromPath(?string $path): ?string
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
