<?php

namespace App\Services\Production;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecipeCostService
{
    /** Compatibility result for the production-orders workflow. */
    public function calculate(Recipe $recipe, int $locationId, float $outputQuantity): array
    {
        $estimate = $this->estimate($recipe, $locationId, $outputQuantity);
        $recipeItems = $recipe->items->keyBy('id');
        $materials = (float) $estimate['material_cost'];

        return [
            'materials' => $materials,
            'labor' => 0.0,
            'overhead' => 0.0,
            'total' => $materials,
            'unit_cost' => $outputQuantity > 0
                ? round($materials / $outputQuantity, 4)
                : 0.0,
            'lines' => collect($estimate['lines'])->map(function (array $line) use ($recipeItems): array {
                $recipeItem = $recipeItems->get($line['recipe_item_id']);

                return [
                    ...$line,
                    'waste_percent' => (float) ($recipeItem?->expected_waste_percent ?? 0),
                    'total_cost' => (float) $line['line_total'],
                ];
            })->all(),
        ];
    }

    public function estimate(
        Recipe $recipe,
        int $locationId,
        ?float $outputQuantity = null
    ): array {
        $recipe->loadMissing([
            'items.ingredient.unitDefinition',
        ]);

        $outputQuantity ??=
            (float) $recipe->yield_quantity;

        $yield =
            max(
                0.001,
                (float) $recipe->yield_quantity
            );

        $factor =
            $outputQuantity / $yield;

        $lines = [];
        $total = 0.0;
        $missing = [];

        foreach ($recipe->items as $item) {
            $quantity =
                $item->grossQuantity(
                    $factor
                );

            $costInfo =
                $this->currentUnitCost(
                    (int) $item->ingredient_product_id,
                    $locationId
                );

            $lineTotal =
                round(
                    $quantity
                    * $costInfo['unit_cost'],
                    4
                );

            $total +=
                $lineTotal;

            if ($costInfo['unit_cost'] <= 0) {
                $missing[] =
                    $item->ingredient
                        ?->name_ar
                    ?: $item->ingredient
                        ?->name
                    ?: '#'
                        . $item
                            ->ingredient_product_id;
            }

            $lines[] = [
                'recipe_item_id' =>
                    $item->id,

                'product_id' =>
                    $item->ingredient_product_id,

                'name' =>
                    $item->ingredient
                        ?->name_ar
                    ?: $item->ingredient
                        ?->name,

                'unit' =>
                    $item->unit_snapshot
                    ?: $item->ingredient
                        ?->unitDefinition
                        ?->symbol
                    ?: $item->ingredient
                        ?->unit,

                'quantity' =>
                    round(
                        $quantity,
                        3
                    ),

                'unit_cost' =>
                    round(
                        $costInfo['unit_cost'],
                        4
                    ),

                'cost_source' =>
                    $costInfo['source'],

                'line_total' =>
                    $lineTotal,
            ];
        }

        return [
            'output_quantity' =>
                round(
                    $outputQuantity,
                    3
                ),

            'factor' =>
                $factor,

            'material_cost' =>
                round(
                    $total,
                    4
                ),

            'unit_material_cost' =>
                $outputQuantity > 0
                    ? round(
                        $total
                        / $outputQuantity,
                        4
                    )
                    : 0,

            'lines' =>
                $lines,

            'missing_costs' =>
                array_values(
                    array_unique(
                        $missing
                    )
                ),

            /*
             * Sprint 06 intentionally calculates material cost only.
             * Labor/overhead accounting belongs to the finance/costing sprint.
             */
            'cost_scope' =>
                'materials_only',
        ];
    }

    public function currentUnitCost(
        int $productId,
        int $locationId
    ): array {
        $inventory =
            Inventory::query()
                ->where(
                    'location_id',
                    $locationId
                )
                ->where(
                    'product_id',
                    $productId
                )
                ->first();

        if (
            $inventory
            && (float) $inventory->unit_cost > 0
        ) {
            return [
                'unit_cost' =>
                    (float)
                    $inventory->unit_cost,

                'source' =>
                    'inventory_weighted_average',
            ];
        }

        if (
            Schema::hasTable(
                'inventory_batches'
            )
        ) {
            $batchCost =
                InventoryBatch::query()
                    ->where(
                        'location_id',
                        $locationId
                    )
                    ->where(
                        'product_id',
                        $productId
                    )
                    ->where(
                        'base_unit_cost',
                        '>',
                        0
                    )
                    ->latest('id')
                    ->value(
                        'base_unit_cost'
                    );

            if ((float) $batchCost > 0) {
                return [
                    'unit_cost' =>
                        (float) $batchCost,

                    'source' =>
                        'latest_inventory_batch',
                ];
            }
        }

        if (
            Schema::hasTable(
                'supplier_product_price_histories'
            )
            && Schema::hasTable(
                'supplier_products'
            )
        ) {
            $purchaseCost =
                DB::table(
                    'supplier_product_price_histories as h'
                )
                    ->join(
                        'supplier_products as sp',
                        'sp.id',
                        '=',
                        'h.supplier_product_id'
                    )
                    ->where(
                        'sp.product_id',
                        $productId
                    )
                    ->where(
                        'h.base_purchase_price',
                        '>',
                        0
                    )
                    ->orderByDesc(
                        'h.effective_at'
                    )
                    ->orderByDesc(
                        'h.id'
                    )
                    ->value(
                        'h.base_purchase_price'
                    );

            if ((float) $purchaseCost > 0) {
                return [
                    'unit_cost' =>
                        (float) $purchaseCost,

                    'source' =>
                        'latest_purchase_cost',
                ];
            }
        }

        return [
            'unit_cost' => 0.0,
            'source' => 'missing',
        ];
    }
}
