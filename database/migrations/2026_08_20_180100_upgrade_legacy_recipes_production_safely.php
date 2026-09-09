<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('units')) {
            throw new \RuntimeException(
                'Sprint 06 requires the Sprint 03 units table. Install Product Architecture 2.0 before Recipes/Production.'
            );
        }

        $this->upgradeRecipes();
        $this->upgradeRecipeItems();
        $this->upgradeProductionOrders();
        $this->upgradeProductionOrderItems();
        $this->backfillRecipeState();
        $this->backfillRecipeUnits();
        $this->backfillProductionSnapshots();
        $this->normalizeLegacyStatuses();
    }

    private function upgradeRecipes(): void
    {
        if (! Schema::hasTable('recipes')) {
            return;
        }

        Schema::table('recipes', function (Blueprint $table): void {
            if (! Schema::hasColumn('recipes', 'output_unit_id')) {
                $table->unsignedBigInteger('output_unit_id')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('recipes', 'status')) {
                $table->string('status', 24)->default('draft')->after('version');
            }
            if (! Schema::hasColumn('recipes', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'activated_by')) {
                $table->unsignedBigInteger('activated_by')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'activated_at')) {
                $table->timestamp('activated_at')->nullable();
            }
        });

        $this->addForeignIfMissing('recipes', 'output_unit_id', 'units', 'recipes_output_unit_fk', 'set null');
        $this->addForeignIfMissing('recipes', 'updated_by', 'users', 'recipes_updated_by_fk', 'set null');
        $this->addForeignIfMissing('recipes', 'activated_by', 'users', 'recipes_activated_by_fk', 'set null');
    }

    private function upgradeRecipeItems(): void
    {
        if (! Schema::hasTable('recipe_items')) {
            return;
        }

        Schema::table('recipe_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('recipe_items', 'unit_id')) {
                $table->unsignedBigInteger('unit_id')->nullable()->after('ingredient_product_id');
            }
            if (! Schema::hasColumn('recipe_items', 'estimated_unit_cost')) {
                $table->decimal('estimated_unit_cost', 16, 4)->nullable()->after('waste_percent');
            }
            if (! Schema::hasColumn('recipe_items', 'is_optional')) {
                $table->boolean('is_optional')->default(false)->after('estimated_unit_cost');
            }
            if (! Schema::hasColumn('recipe_items', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_optional');
            }
        });

        $this->addForeignIfMissing('recipe_items', 'unit_id', 'units', 'recipe_items_unit_fk', 'set null');
    }

    private function upgradeProductionOrders(): void
    {
        if (! Schema::hasTable('production_orders')) {
            return;
        }

        Schema::table('production_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_orders', 'output_unit_id')) {
                $table->unsignedBigInteger('output_unit_id')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('production_orders', 'recipe_version')) {
                $table->unsignedInteger('recipe_version')->nullable()->after('output_unit_id');
            }
            if (! Schema::hasColumn('production_orders', 'recipe_yield_quantity')) {
                $table->decimal('recipe_yield_quantity', 14, 4)->default(1)->after('status');
            }
            if (! Schema::hasColumn('production_orders', 'output_variance_quantity')) {
                $table->decimal('output_variance_quantity', 14, 4)->nullable()->after('actual_output_quantity');
            }
            if (! Schema::hasColumn('production_orders', 'output_variance_percent')) {
                $table->decimal('output_variance_percent', 10, 4)->nullable()->after('output_variance_quantity');
            }
            if (! Schema::hasColumn('production_orders', 'estimated_material_cost')) {
                $table->decimal('estimated_material_cost', 18, 4)->default(0);
            }
            if (! Schema::hasColumn('production_orders', 'actual_material_cost')) {
                $table->decimal('actual_material_cost', 18, 4)->default(0);
            }
            if (! Schema::hasColumn('production_orders', 'overhead_percent_snapshot')) {
                $table->decimal('overhead_percent_snapshot', 8, 3)->default(0);
            }
            if (! Schema::hasColumn('production_orders', 'cost_is_complete')) {
                $table->boolean('cost_is_complete')->default(false);
            }
            if (! Schema::hasColumn('production_orders', 'released_at')) {
                $table->timestamp('released_at')->nullable();
            }
            if (! Schema::hasColumn('production_orders', 'released_by')) {
                $table->unsignedBigInteger('released_by')->nullable();
            }
        });

        $this->addForeignIfMissing('production_orders', 'output_unit_id', 'units', 'prod_orders_output_unit_fk', 'set null');
        $this->addForeignIfMissing('production_orders', 'released_by', 'users', 'prod_orders_released_by_fk', 'set null');
    }

    private function upgradeProductionOrderItems(): void
    {
        if (! Schema::hasTable('production_order_items')) {
            return;
        }

        Schema::table('production_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_order_items', 'recipe_item_id')) {
                $table->unsignedBigInteger('recipe_item_id')->nullable()->after('production_order_id');
            }
            if (! Schema::hasColumn('production_order_items', 'unit_id')) {
                $table->unsignedBigInteger('unit_id')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('production_order_items', 'recipe_quantity')) {
                $table->decimal('recipe_quantity', 14, 4)->default(0);
            }
            if (! Schema::hasColumn('production_order_items', 'waste_percent_snapshot')) {
                $table->decimal('waste_percent_snapshot', 8, 3)->default(0);
            }
            if (! Schema::hasColumn('production_order_items', 'issued_quantity')) {
                $table->decimal('issued_quantity', 14, 4)->default(0);
            }
            if (! Schema::hasColumn('production_order_items', 'returned_quantity')) {
                $table->decimal('returned_quantity', 14, 4)->default(0);
            }
            if (! Schema::hasColumn('production_order_items', 'waste_quantity')) {
                $table->decimal('waste_quantity', 14, 4)->default(0);
            }
            if (! Schema::hasColumn('production_order_items', 'unit_cost_snapshot')) {
                $table->decimal('unit_cost_snapshot', 16, 4)->nullable();
            }
            if (! Schema::hasColumn('production_order_items', 'planned_cost')) {
                $table->decimal('planned_cost', 18, 4)->default(0);
            }
            if (! Schema::hasColumn('production_order_items', 'actual_cost')) {
                $table->decimal('actual_cost', 18, 4)->default(0);
            }
            if (! Schema::hasColumn('production_order_items', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        $this->addForeignIfMissing('production_order_items', 'recipe_item_id', 'recipe_items', 'prod_items_recipe_item_fk', 'set null');
        $this->addForeignIfMissing('production_order_items', 'unit_id', 'units', 'prod_items_unit_fk', 'set null');
    }

    private function backfillRecipeState(): void
    {
        if (! Schema::hasTable('recipes')) {
            return;
        }

        if (Schema::hasColumn('recipes', 'is_active')) {
            DB::table('recipes')
                ->where('is_active', true)
                ->update(['status' => 'approved']);

            DB::table('recipes')
                ->where('is_active', false)
                ->where('status', '!=', 'approved')
                ->update(['status' => 'archived']);
        }

        // If legacy data accidentally has multiple active versions, keep only
        // the newest one active and archive older versions without deleting them.
        $products = DB::table('recipes')
            ->select('product_id')
            ->where('status', 'approved')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_id');

        foreach ($products as $productId) {
            $keepId = DB::table('recipes')
                ->where('product_id', $productId)
                ->where('status', 'approved')
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->value('id');

            DB::table('recipes')
                ->where('product_id', $productId)
                ->where('status', 'approved')
                ->where('id', '!=', $keepId)
                ->update(['status' => 'archived']);
        }
    }

    private function backfillRecipeUnits(): void
    {
        if (! Schema::hasTable('recipes') || ! Schema::hasTable('products')) {
            return;
        }

        DB::table('recipes')
            ->whereNull('output_unit_id')
            ->orderBy('id')
            ->chunkById(200, function ($recipes): void {
                foreach ($recipes as $recipe) {
                    $unitId = DB::table('products')
                        ->where('id', $recipe->product_id)
                        ->value('unit_id');

                    if ($unitId) {
                        DB::table('recipes')
                            ->where('id', $recipe->id)
                            ->update(['output_unit_id' => $unitId]);
                    }
                }
            });

        DB::table('recipe_items')
            ->whereNull('unit_id')
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    $unitId = DB::table('products')
                        ->where('id', $item->ingredient_product_id)
                        ->value('unit_id');

                    if ($unitId) {
                        DB::table('recipe_items')
                            ->where('id', $item->id)
                            ->update(['unit_id' => $unitId]);
                    }
                }
            });
    }

    private function backfillProductionSnapshots(): void
    {
        if (! Schema::hasTable('production_orders')) {
            return;
        }

        DB::table('production_orders')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $recipe = DB::table('recipes')
                        ->where('id', $order->recipe_id)
                        ->first();

                    if (! $recipe) {
                        continue;
                    }

                    $updates = [
                        'recipe_version' => $order->recipe_version ?? $recipe->version ?? 1,
                        'recipe_yield_quantity' => $order->recipe_yield_quantity ?? $recipe->yield_quantity ?? 1,
                        'output_unit_id' => $order->output_unit_id ?? $recipe->output_unit_id ?? null,
                    ];

                    if (Schema::hasColumn('production_orders', 'material_cost')) {
                        $updates['estimated_material_cost'] = $order->estimated_material_cost ?: ($order->material_cost ?? 0);
                        $updates['actual_material_cost'] = $order->actual_material_cost ?: (($order->status ?? '') === 'completed' ? ($order->material_cost ?? 0) : 0);
                    }

                    $updates['overhead_percent_snapshot'] = $order->overhead_percent_snapshot ?: ($recipe->overhead_percent ?? 0);
                    $updates['cost_is_complete'] = false;

                    DB::table('production_orders')
                        ->where('id', $order->id)
                        ->update($updates);
                }
            });

        if (! Schema::hasTable('production_order_items')) {
            return;
        }

        DB::table('production_order_items')
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    $order = DB::table('production_orders')
                        ->where('id', $item->production_order_id)
                        ->first();

                    if (! $order) {
                        continue;
                    }

                    $recipeItem = DB::table('recipe_items')
                        ->where('recipe_id', $order->recipe_id)
                        ->where('ingredient_product_id', $item->product_id)
                        ->first();

                    $productUnitId = DB::table('products')
                        ->where('id', $item->product_id)
                        ->value('unit_id');

                    $legacyUnitCost = Schema::hasColumn('production_order_items', 'unit_cost')
                        ? (float) ($item->unit_cost ?? 0)
                        : null;

                    $legacyTotalCost = Schema::hasColumn('production_order_items', 'total_cost')
                        ? (float) ($item->total_cost ?? 0)
                        : 0.0;

                    $status = (string) $order->status;
                    $wasIssued = in_array($status, ['in_progress', 'completed'], true);

                    DB::table('production_order_items')
                        ->where('id', $item->id)
                        ->update([
                            'recipe_item_id' => $item->recipe_item_id ?? $recipeItem?->id,
                            'unit_id' => $item->unit_id ?? $recipeItem?->unit_id ?? $productUnitId,
                            'recipe_quantity' => $item->recipe_quantity ?: ($recipeItem?->quantity ?? $item->planned_quantity),
                            'waste_percent_snapshot' => $item->waste_percent_snapshot ?: ($recipeItem?->waste_percent ?? 0),
                            'issued_quantity' => $item->issued_quantity ?: ($wasIssued ? $item->planned_quantity : 0),
                            'actual_quantity' => $item->actual_quantity ?? ($status === 'completed' ? $item->planned_quantity : null),
                            'unit_cost_snapshot' => $item->unit_cost_snapshot ?? $legacyUnitCost,
                            'planned_cost' => $item->planned_cost ?: $legacyTotalCost,
                            'actual_cost' => $item->actual_cost ?: ($status === 'completed' ? $legacyTotalCost : 0),
                        ]);
                }
            });
    }

    private function normalizeLegacyStatuses(): void
    {
        if (! Schema::hasTable('production_orders')) {
            return;
        }

        DB::table('production_orders')
            ->where('status', 'planned')
            ->update([
                'status' => 'released',
                'released_at' => DB::raw('COALESCE(released_at, planned_at, created_at)'),
            ]);
    }

    private function addForeignIfMissing(
        string $table,
        string $column,
        string $referencedTable,
        string $constraint,
        string $onDelete
    ): void {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use (
            $column,
            $referencedTable,
            $constraint,
            $onDelete
        ): void {
            $foreign = $blueprint
                ->foreign($column, $constraint)
                ->references('id')
                ->on($referencedTable);

            if ($onDelete === 'set null') {
                $foreign->nullOnDelete();
            } else {
                $foreign->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Deliberately non-destructive. This migration upgrades possible legacy
        // Sprint-06 data; rolling it back must never remove preserved data.
    }
};
