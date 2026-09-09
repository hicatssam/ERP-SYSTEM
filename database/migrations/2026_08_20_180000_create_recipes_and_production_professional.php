<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('output_unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->string('name', 160);
                $table->unsignedInteger('version');
                $table->string('status', 24)->default('draft');
                $table->decimal('yield_quantity', 14, 4)->default(1);
                $table->decimal('labor_cost_per_batch', 16, 4)->default(0);
                $table->decimal('overhead_percent', 8, 3)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['product_id', 'version'], 'recipes_product_version_uq');
                $table->index(['product_id', 'status'], 'recipes_product_status_idx');
            });
        }

        if (! Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
                $table->foreignId('ingredient_product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->decimal('quantity', 14, 4);
                $table->decimal('waste_percent', 8, 3)->default(0);
                $table->decimal('estimated_unit_cost', 16, 4)->nullable();
                $table->boolean('is_optional')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['recipe_id', 'ingredient_product_id'],
                    'recipe_items_recipe_ingredient_uq'
                );
                $table->index(['recipe_id', 'sort_order'], 'recipe_items_sort_idx');
            });
        }

        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table): void {
                $table->id();
                $table->string('production_number', 70)->unique();
                $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
                $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('output_unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->unsignedInteger('recipe_version');
                $table->string('status', 24)->default('draft');

                $table->decimal('recipe_yield_quantity', 14, 4);
                $table->decimal('planned_output_quantity', 14, 4);
                $table->decimal('actual_output_quantity', 14, 4)->nullable();
                $table->decimal('output_variance_quantity', 14, 4)->nullable();
                $table->decimal('output_variance_percent', 10, 4)->nullable();

                $table->decimal('estimated_material_cost', 18, 4)->default(0);
                $table->decimal('actual_material_cost', 18, 4)->default(0);
                $table->decimal('labor_cost', 18, 4)->default(0);
                $table->decimal('overhead_percent_snapshot', 8, 3)->default(0);
                $table->decimal('overhead_cost', 18, 4)->default(0);
                $table->decimal('total_cost', 18, 4)->default(0);
                $table->decimal('unit_cost', 18, 6)->default(0);
                $table->boolean('cost_is_complete')->default(false);

                $table->timestamp('planned_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(
                    ['location_id', 'status', 'planned_at'],
                    'production_orders_scope_idx'
                );
                $table->index(['product_id', 'status'], 'production_orders_product_idx');
            });
        }

        if (! Schema::hasTable('production_order_items')) {
            Schema::create('production_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_order_id')
                    ->constrained('production_orders')
                    ->cascadeOnDelete();
                $table->foreignId('recipe_item_id')
                    ->nullable()
                    ->constrained('recipe_items')
                    ->nullOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

                $table->decimal('recipe_quantity', 14, 4);
                $table->decimal('waste_percent_snapshot', 8, 3)->default(0);
                $table->decimal('planned_quantity', 14, 4);
                $table->decimal('issued_quantity', 14, 4)->default(0);
                $table->decimal('actual_quantity', 14, 4)->nullable();
                $table->decimal('returned_quantity', 14, 4)->default(0);
                $table->decimal('waste_quantity', 14, 4)->default(0);

                $table->decimal('unit_cost_snapshot', 16, 4)->nullable();
                $table->decimal('planned_cost', 18, 4)->default(0);
                $table->decimal('actual_cost', 18, 4)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['production_order_id', 'product_id'],
                    'production_items_order_product_uq'
                );
                $table->index(
                    ['production_order_id', 'product_id'],
                    'production_items_lookup_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
