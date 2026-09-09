<?php

namespace Tests\Feature\Restaurant;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use App\Models\ProductVariant;
use App\Services\Restaurant\RestaurantOrderCustomizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CafeCustomizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_and_modifier_prices_are_resolved_server_side(): void
    {
        $category = Category::query()->create([
            'name' => 'Drinks',
            'name_ar' => 'مشروبات',
            'slug' => 'drinks',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Latte',
            'name_ar' => 'لاتيه',
            'sku' => 'LATTE',
            'barcode' => '6291100000012',
            'product_type' => 'variant',
            'base_selling_price' => 10,
            'is_active' => true,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Medium',
            'name_ar' => 'وسط',
            'sku' => 'LATTE-M',
            'selling_price' => 12,
            'is_active' => true,
            'is_default' => true,
        ]);

        $group = ModifierGroup::query()->create([
            'code' => 'milk',
            'name' => 'Milk',
            'name_ar' => 'الحليب',
            'selection_type' => 'single',
            'is_active' => true,
        ]);

        $modifier = Modifier::query()->create([
            'modifier_group_id' => $group->id,
            'code' => 'oat-milk',
            'name' => 'Oat Milk',
            'name_ar' => 'حليب شوفان',
            'price_delta' => 2,
            'is_active' => true,
        ]);

        ProductModifierGroup::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'modifier_group_id' => $group->id,
            'is_active' => true,
        ]);

        $resolved = app(RestaurantOrderCustomizationService::class)->resolve(
            $product,
            [
                'product_variant_id' => $variant->id,
                'modifiers' => [
                    ['modifier_id' => $modifier->id, 'quantity' => 1],
                ],
            ],
            1
        );

        $this->assertSame($variant->id, $resolved['variant']->id);
        $this->assertSame('وسط', $resolved['variant_name']);
        $this->assertSame(14.0, $resolved['unit_price']);
        $this->assertCount(1, $resolved['modifiers']);
    }

    public function test_variant_product_rejects_missing_variant(): void
    {
        $category = Category::query()->create([
            'name' => 'Coffee',
            'slug' => 'coffee',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Cappuccino',
            'sku' => 'CAP',
            'barcode' => '6291100000029',
            'product_type' => 'variant',
            'base_selling_price' => 10,
            'is_active' => true,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'CAP-L',
            'selling_price' => 15,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(RestaurantOrderCustomizationService::class)->resolve(
            $product,
            [],
            1
        );
    }
}
