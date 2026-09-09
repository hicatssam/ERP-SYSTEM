<?php

namespace App\Services\Production;

use App\Enums\RecipeStatus;
use App\Models\Recipe;
use Illuminate\Validation\ValidationException;

class RecipeValidationService
{
    public function assertApprovable(Recipe $recipe): void
    {
        $recipe->loadMissing('items');

        if ($recipe->items->isEmpty()) {
            throw ValidationException::withMessages([
                'recipe' => 'لا يمكن اعتماد وصفة بدون مكونات.',
            ]);
        }

        if ((float) $recipe->yield_quantity <= 0) {
            throw ValidationException::withMessages([
                'yield_quantity' => 'ناتج الوصفة يجب أن يكون أكبر من صفر.',
            ]);
        }

        foreach ($recipe->items as $item) {
            if ((float) $item->quantity <= 0) {
                throw ValidationException::withMessages([
                    'recipe' => 'كل كمية مكون يجب أن تكون أكبر من صفر.',
                ]);
            }

            if (
                (int) $item->ingredient_product_id
                ===
                (int) $recipe->product_id
            ) {
                throw ValidationException::withMessages([
                    'recipe' => 'لا يمكن أن يحتوي المنتج على نفسه كمكون.',
                ]);
            }

            if (
                $this->productDependsOn(
                    (int) $item->ingredient_product_id,
                    (int) $recipe->product_id,
                    []
                )
            ) {
                throw ValidationException::withMessages([
                    'recipe' =>
                        'تعذر اعتماد الوصفة لأنها تنشئ حلقة BOM/وصفات بين المنتجات.',
                ]);
            }
        }
    }

    private function productDependsOn(
        int $productId,
        int $targetProductId,
        array $visited
    ): bool {
        if ($productId === $targetProductId) {
            return true;
        }

        if (isset($visited[$productId])) {
            return false;
        }

        $visited[$productId] = true;

        $recipes = Recipe::query()
            ->where('product_id', $productId)
            ->where('status', RecipeStatus::Approved->value)
            ->where('is_active', true)
            ->with('items:id,recipe_id,ingredient_product_id')
            ->get();

        foreach ($recipes as $recipe) {
            foreach ($recipe->items as $item) {
                if (
                    $this->productDependsOn(
                        (int) $item->ingredient_product_id,
                        $targetProductId,
                        $visited
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
