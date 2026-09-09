<?php

namespace App\Services\Restaurant;

use App\Enums\ModifierSelectionType;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RestaurantOrderCustomizationService
{
    /**
     * Resolve one POS line from trusted database values only.
     * Client prices/names are never accepted.
     *
     * @return array{
     *   variant: ?ProductVariant,
     *   variant_name: ?string,
     *   base_price: float,
     *   unit_price: float,
     *   modifiers: array<int, array<string, mixed>>
     * }
     */
    public function resolve(
        Product $product,
        array $item,
        int $locationId
    ): array {
        $variant = $this->resolveVariant($product, $item['product_variant_id'] ?? null);

        $basePrice = $variant?->selling_price !== null
            ? (float) $variant->selling_price
            : (float) $product->getEffectivePriceForLocation($locationId);

        $links = $this->applicableGroupLinks($product, $variant);
        $selectedRows = collect($item['modifiers'] ?? [])->values();
        $resolvedModifiers = $this->resolveModifiers($links, $selectedRows);

        $modifierDelta = collect($resolvedModifiers)->sum(
            fn (array $row): float => (float) $row['price_delta'] * (int) $row['quantity']
        );

        return [
            'variant' => $variant,
            'variant_name' => $variant?->displayName(),
            'base_price' => round($basePrice, 3),
            'unit_price' => round($basePrice + $modifierDelta, 3),
            'modifiers' => $resolvedModifiers,
        ];
    }

    private function resolveVariant(Product $product, mixed $variantId): ?ProductVariant
    {
        if ($variantId === null || $variantId === '') {
            if ($product->isVariantProduct() && $product->activeVariants()->exists()) {
                throw ValidationException::withMessages([
                    'items' => "يجب اختيار متغير/حجم للمنتج \"{$this->productName($product)}\".",
                ]);
            }

            return null;
        }

        $variant = ProductVariant::query()
            ->active()
            ->where('product_id', $product->id)
            ->with(['size', 'color', 'attributeValues.attribute'])
            ->find((int) $variantId);

        if (! $variant) {
            throw ValidationException::withMessages([
                'items' => "المتغير المحدد لا يتبع المنتج \"{$this->productName($product)}\" أو أنه غير فعال.",
            ]);
        }

        return $variant;
    }

    /** @return Collection<int, ProductModifierGroup> */
    private function applicableGroupLinks(Product $product, ?ProductVariant $variant): Collection
    {
        $query = ProductModifierGroup::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with(['group.modifiers' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);

        if ($variant) {
            $query->where(function ($q) use ($variant): void {
                $q->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', $variant->id);
            });
        } else {
            $query->whereNull('product_variant_id');
        }

        $links = $query
            ->orderByRaw('CASE WHEN product_variant_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // A variant-specific link overrides the product-level link for same group.
        return $links->unique('modifier_group_id')->values();
    }

    /**
     * @param Collection<int, ProductModifierGroup> $links
     * @param Collection<int, mixed> $selectedRows
     * @return array<int, array<string, mixed>>
     */
    private function resolveModifiers(Collection $links, Collection $selectedRows): array
    {
        $groupLinks = $links->keyBy('modifier_group_id');
        $normalized = [];

        foreach ($selectedRows as $row) {
            if (! is_array($row) || empty($row['modifier_id'])) {
                continue;
            }

            $id = (int) $row['modifier_id'];
            $qty = max(1, (int) ($row['quantity'] ?? 1));

            if (isset($normalized[$id])) {
                throw ValidationException::withMessages([
                    'items' => 'لا يجوز تكرار نفس الإضافة أكثر من مرة في سطر الطلب.',
                ]);
            }

            $normalized[$id] = $qty;
        }

        $modifiers = Modifier::query()
            ->whereIn('id', array_keys($normalized))
            ->where('is_active', true)
            ->with('group')
            ->get()
            ->keyBy('id');

        if ($modifiers->count() !== count($normalized)) {
            throw ValidationException::withMessages([
                'items' => 'إحدى الإضافات المحددة غير موجودة أو غير فعالة.',
            ]);
        }

        $selectedByGroup = collect();
        $result = [];

        foreach ($normalized as $modifierId => $qty) {
            /** @var Modifier|null $modifier */
            $modifier = $modifiers->get($modifierId);
            $groupId = (int) $modifier->modifier_group_id;
            $link = $groupLinks->get($groupId);

            if (! $link || ! $link->group?->is_active) {
                throw ValidationException::withMessages([
                    'items' => "الإضافة \"{$this->modifierName($modifier)}\" غير متاحة لهذا المنتج/الحجم.",
                ]);
            }

            if (! $modifier->allow_quantity && $qty !== 1) {
                throw ValidationException::withMessages([
                    'items' => "الإضافة \"{$this->modifierName($modifier)}\" لا تسمح بتغيير الكمية.",
                ]);
            }

            if ($qty > max(1, (int) $modifier->max_quantity)) {
                throw ValidationException::withMessages([
                    'items' => "كمية الإضافة \"{$this->modifierName($modifier)}\" تتجاوز الحد المسموح.",
                ]);
            }

            $selectedByGroup->push([
                'group_id' => $groupId,
                'modifier_id' => (int) $modifier->id,
            ]);

            $result[] = [
                'modifier_group_id' => $groupId,
                'modifier_id' => (int) $modifier->id,
                'group_name' => $link->group->name_ar ?: $link->group->name,
                'modifier_name' => $this->modifierName($modifier),
                'price_delta' => (float) $modifier->price_delta,
                'quantity' => $qty,
                'configuration' => $modifier->configuration,
            ];
        }

        foreach ($links as $link) {
            $group = $link->group;
            if (! $group || ! $group->is_active) {
                continue;
            }

            $count = $selectedByGroup
                ->where('group_id', (int) $group->id)
                ->count();

            $required = $link->is_required_override ?? (bool) $group->is_required;
            $min = $link->min_selections_override ?? (int) $group->min_selections;
            $max = $link->max_selections_override ?? $group->max_selections;

            if ($required && $min < 1) {
                $min = 1;
            }

            if ($group->selection_type === ModifierSelectionType::Single) {
                $max = 1;
            }

            $groupName = $group->name_ar ?: $group->name;

            if ($count < $min) {
                throw ValidationException::withMessages([
                    'items' => "مجموعة \"{$groupName}\" تتطلب اختيار {$min} على الأقل.",
                ]);
            }

            if ($max !== null && $count > (int) $max) {
                throw ValidationException::withMessages([
                    'items' => "مجموعة \"{$groupName}\" تسمح بحد أقصى {$max} اختيار/اختيارات.",
                ]);
            }
        }

        return $result;
    }

    private function productName(Product $product): string
    {
        return $product->name_ar ?: $product->name ?: ('#' . $product->id);
    }

    private function modifierName(Modifier $modifier): string
    {
        return $modifier->name_ar ?: $modifier->name ?: ('#' . $modifier->id);
    }
}
