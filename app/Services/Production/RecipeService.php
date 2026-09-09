<?php

namespace App\Services\Production;

use App\Enums\RecipeStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeService
{
    public function __construct(
        private RecipeValidationService $validator
    ) {
    }

    public function create(
        array $data,
        User $user
    ): Recipe {
        return DB::transaction(
            function () use (
                $data,
                $user
            ): Recipe {
                $product =
                    Product::query()
                        ->whereKey(
                            $data['product_id']
                        )
                        ->lockForUpdate()
                        ->with(
                            'unitDefinition'
                        )
                        ->firstOrFail();

                $variant = $this->resolveVariant(
                    $product,
                    $data['product_variant_id'] ?? null
                );

                $version =
                    (int)
                    Recipe::query()
                        ->where(
                            'product_id',
                            $product->id
                        )
                        ->max('version')
                    + 1;

                $recipe =
                    Recipe::query()
                        ->create([
                            'code' =>
                                $variant
                                    ? sprintf(
                                        'RCP-%d-PV%d-V%d',
                                        $product->id,
                                        $variant->id,
                                        $version
                                    )
                                    : sprintf(
                                        'RCP-%d-V%d',
                                        $product->id,
                                        $version
                                    ),

                            'product_id' =>
                                $product->id,

                            'product_variant_id' =>
                                $variant?->id,

                            'name' =>
                                $data['name'],

                            'version' =>
                                $version,

                            'yield_quantity' =>
                                $data[
                                    'yield_quantity'
                                ],

                            'status' =>
                                RecipeStatus::Draft,

                            'is_active' =>
                                false,

                            'notes' =>
                                $data['notes']
                                ?? null,

                            'created_by' =>
                                $user->id,
                        ]);

                $this->replaceItems(
                    $recipe,
                    $data['items']
                    ?? []
                );

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'recipe.created',

                    module:
                        'recipes',

                    recordType:
                        'recipes',

                    recordId:
                        $recipe->id,

                    newValues:
                        [
                            'product_id' =>
                                $recipe
                                    ->product_id,

                            'product_variant_id' =>
                                $recipe
                                    ->product_variant_id,

                            'version' =>
                                $recipe
                                    ->version,

                            'yield_quantity' =>
                                $recipe
                                    ->yield_quantity,
                        ],
                );

                return $recipe
                    ->fresh([
                        'product',
                        'productVariant',
                        'items.ingredient',
                    ]);
            }
        );
    }

    public function updateDraft(
        Recipe $recipe,
        array $data,
        User $user
    ): Recipe {
        if (! $recipe->isEditable()) {
            throw ValidationException::withMessages([
                'recipe' =>
                    'الوصفة المعتمدة لا تعدّل مباشرة. أنشئ إصدارًا جديدًا للحفاظ على السجل التاريخي.',
            ]);
        }

        return DB::transaction(
            function () use (
                $recipe,
                $data,
                $user
            ): Recipe {
                $before =
                    $recipe->only([
                        'name',
                        'yield_quantity',
                        'notes',
                    ]);

                $recipe->update([
                    'name' =>
                        $data['name'],

                    'yield_quantity' =>
                        $data[
                            'yield_quantity'
                        ],

                    'notes' =>
                        $data['notes']
                        ?? null,
                ]);

                $this->replaceItems(
                    $recipe,
                    $data['items']
                    ?? []
                );

                ActivityLogger::logChange(
                    userId:
                        $user->id,

                    action:
                        'recipe.updated',

                    module:
                        'recipes',

                    recordType:
                        'recipes',

                    recordId:
                        $recipe->id,

                    before:
                        $before,

                    after:
                        $recipe->fresh()
                            ->only([
                                'name',
                                'yield_quantity',
                                'notes',
                            ]),
                );

                return $recipe
                    ->fresh([
                        'product',
                        'items.ingredient',
                    ]);
            }
        );
    }

    public function createNewVersion(
        Recipe $source,
        User $user
    ): Recipe {
        $source->loadMissing(
            'items'
        );

        return DB::transaction(
            function () use (
                $source,
                $user
            ): Recipe {
                Product::query()
                    ->whereKey(
                        $source->product_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $version =
                    (int)
                    Recipe::query()
                        ->where(
                            'product_id',
                            $source
                                ->product_id
                        )
                        ->max('version')
                    + 1;

                $copy =
                    Recipe::query()
                        ->create([
                            'code' =>
                                $source->product_variant_id
                                    ? sprintf(
                                        'RCP-%d-PV%d-V%d',
                                        $source->product_id,
                                        $source->product_variant_id,
                                        $version
                                    )
                                    : sprintf(
                                        'RCP-%d-V%d',
                                        $source->product_id,
                                        $version
                                    ),

                            'product_id' =>
                                $source
                                    ->product_id,

                            'product_variant_id' =>
                                $source
                                    ->product_variant_id,

                            'name' =>
                                $source
                                    ->name,

                            'version' =>
                                $version,

                            'yield_quantity' =>
                                $source
                                    ->yield_quantity,

                            'status' =>
                                RecipeStatus::Draft,

                            'is_active' =>
                                false,

                            'notes' =>
                                $source
                                    ->notes,

                            'created_by' =>
                                $user->id,
                        ]);

                foreach (
                    $source->items as $item
                ) {
                    $copy->items()
                        ->create([
                            'ingredient_product_id' =>
                                $item
                                    ->ingredient_product_id,

                            'quantity' =>
                                $item
                                    ->quantity,

                            'expected_waste_percent' =>
                                $item
                                    ->expected_waste_percent,

                            'unit_snapshot' =>
                                $item
                                    ->unit_snapshot,

                            'stage' =>
                                $item
                                    ->stage,

                            'notes' =>
                                $item
                                    ->notes,

                            'sort_order' =>
                                $item
                                    ->sort_order,
                        ]);
                }

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'recipe.version_created',

                    module:
                        'recipes',

                    recordType:
                        'recipes',

                    recordId:
                        $copy->id,

                    metadata:
                        [
                            'source_recipe_id' =>
                                $source->id,

                            'source_version' =>
                                $source
                                    ->version,

                            'new_version' =>
                                $version,
                        ],
                );

                return $copy
                    ->fresh([
                        'product',
                        'productVariant',
                        'items.ingredient',
                    ]);
            }
        );
    }

    public function approve(
        Recipe $recipe,
        User $user
    ): Recipe {
        if (! $recipe->isEditable()) {
            throw ValidationException::withMessages([
                'recipe' =>
                    'يمكن اعتماد الوصفات المسودة فقط.',
            ]);
        }

        $this->validator
            ->assertApprovable(
                $recipe
            );

        return DB::transaction(
            function () use (
                $recipe,
                $user
            ): Recipe {
                $locked =
                    Recipe::query()
                        ->whereKey(
                            $recipe->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                Product::query()
                    ->whereKey(
                        $locked->product_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                Recipe::query()
                    ->where(
                        'product_id',
                        $locked
                            ->product_id
                    )
                    ->when(
                        $locked->product_variant_id,
                        fn ($query, $variantId) => $query->where('product_variant_id', $variantId),
                        fn ($query) => $query->whereNull('product_variant_id')
                    )
                    ->where(
                        'id',
                        '!=',
                        $locked->id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->update([
                        'is_active' =>
                            false,
                    ]);

                $locked->update([
                    'status' =>
                        RecipeStatus::Approved,

                    'is_active' =>
                        true,

                    'approved_by' =>
                        $user->id,

                    'approved_at' =>
                        now(),
                ]);

                ActivityLogger::log(
                    userId:
                        $user->id,

                    action:
                        'recipe.approved',

                    module:
                        'recipes',

                    recordType:
                        'recipes',

                    recordId:
                        $locked->id,

                    oldValues:
                        [
                            'status' =>
                                RecipeStatus::Draft
                                    ->value,
                        ],

                    newValues:
                        [
                            'status' =>
                                RecipeStatus::Approved
                                    ->value,

                            'is_active' =>
                                true,
                        ],
                );

                return $locked
                    ->fresh([
                        'product',
                        'items.ingredient',
                    ]);
            }
        );
    }

    public function archive(
        Recipe $recipe,
        User $user
    ): Recipe {
        if (
            $recipe->productionBatches()
                ->whereNotIn(
                    'status',
                    [
                        'completed',
                        'rejected',
                        'cancelled',
                    ]
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'recipe' =>
                    'لا يمكن أرشفة وصفة مرتبطة بدفعة إنتاج مفتوحة.',
            ]);
        }

        $before =
            [
                'status' =>
                    $recipe->status
                        ->value,

                'is_active' =>
                    $recipe
                        ->is_active,
            ];

        $recipe->update([
            'status' =>
                RecipeStatus::Archived,

            'is_active' =>
                false,
        ]);

        ActivityLogger::log(
            userId:
                $user->id,

            action:
                'recipe.archived',

            module:
                'recipes',

            recordType:
                'recipes',

            recordId:
                $recipe->id,

            oldValues:
                $before,

            newValues:
                [
                    'status' =>
                        RecipeStatus::Archived
                            ->value,

                    'is_active' =>
                        false,
                ],
        );

        return $recipe->fresh();
    }

    private function resolveVariant(Product $product, mixed $variantId): ?ProductVariant
    {
        if ($variantId === null || $variantId === '') {
            return null;
        }

        $variant = ProductVariant::query()
            ->active()
            ->where('product_id', $product->id)
            ->find((int) $variantId);

        if (! $variant) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'المتغير/الحجم المحدد لا يتبع المنتج الناتج أو أنه غير فعال.',
            ]);
        }

        return $variant;
    }

    private function replaceItems(
        Recipe $recipe,
        array $items
    ): void {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' =>
                    'أضف مكونًا واحدًا على الأقل للوصفة.',
            ]);
        }

        $recipe->items()
            ->delete();

        foreach (
            array_values(
                $items
            ) as $index => $item
        ) {
            $ingredient =
                Product::query()
                    ->with(
                        'unitDefinition'
                    )
                    ->findOrFail(
                        $item[
                            'ingredient_product_id'
                        ]
                    );

            if (
                (int) $ingredient->id
                ===
                (int) $recipe->product_id
            ) {
                throw ValidationException::withMessages([
                    'items' =>
                        'لا يمكن إضافة المنتج الناتج كمكون داخل وصفته.',
                ]);
            }

            $unit =
                $ingredient
                    ->unitDefinition
                    ?->symbol
                ?: $ingredient
                    ->unitDefinition
                    ?->displayName()
                ?: $ingredient
                    ->unit;

            $recipe->items()
                ->create([
                    'ingredient_product_id' =>
                        $ingredient->id,

                    'quantity' =>
                        $item[
                            'quantity'
                        ],

                    'expected_waste_percent' =>
                        $item[
                            'expected_waste_percent'
                        ]
                        ?? 0,

                    'unit_snapshot' =>
                        $unit,

                    'stage' =>
                        $item['stage']
                        ?? null,

                    'notes' =>
                        $item['notes']
                        ?? null,

                    'sort_order' =>
                        ($index + 1)
                        * 10,
                ]);
        }
    }
}
