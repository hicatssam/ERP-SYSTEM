<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('name', 180);
                $table->unsignedInteger('version')->default(1);
                $table->decimal('yield_quantity', 14, 4)->default(1);
                $table->decimal('labor_cost_per_batch', 16, 4)->default(0);
                $table->decimal('overhead_percent', 8, 4)->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['product_id', 'version'], 'recipe_product_version_uq');
                $table->index(['product_id', 'is_active'], 'recipe_product_active_idx');

                $table->foreign('product_id', 'recipe_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();

                $table->foreign('created_by', 'recipe_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->foreign('updated_by', 'recipe_updated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('ingredient_product_id');
                $table->unsignedBigInteger('recipe_unit_id')->nullable();
                $table->decimal('quantity', 14, 6);
                $table->decimal('conversion_to_stock_unit', 16, 8)->default(1);
                $table->decimal('waste_percent', 8, 4)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['recipe_id', 'ingredient_product_id'], 'recipe_item_ingredient_uq');

                $table->foreign('recipe_id', 'recipe_item_recipe_fk')
                    ->references('id')->on('recipes')->cascadeOnDelete();

                $table->foreign('ingredient_product_id', 'recipe_item_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();

                $table->foreign('recipe_unit_id', 'recipe_item_unit_fk')
                    ->references('id')->on('units')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table) {
                $table->id();
                $table->string('production_number', 70)->unique();
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('product_id');
                $table->string('status', 30)->default('planned');

                $table->decimal('planned_output_quantity', 14, 4);
                $table->decimal('actual_output_quantity', 14, 4)->nullable();

                $table->decimal('planned_material_cost', 16, 4)->default(0);
                $table->decimal('planned_labor_cost', 16, 4)->default(0);
                $table->decimal('planned_overhead_cost', 16, 4)->default(0);
                $table->decimal('planned_total_cost', 16, 4)->default(0);
                $table->decimal('planned_unit_cost', 16, 6)->default(0);

                $table->decimal('actual_material_cost', 16, 4)->nullable();
                $table->decimal('actual_labor_cost', 16, 4)->nullable();
                $table->decimal('actual_overhead_cost', 16, 4)->nullable();
                $table->decimal('actual_total_cost', 16, 4)->nullable();
                $table->decimal('actual_unit_cost', 16, 6)->nullable();

                $table->decimal('yield_variance_quantity', 14, 4)->nullable();
                $table->decimal('yield_variance_percent', 10, 4)->nullable();

                $table->timestamp('planned_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('started_by')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['location_id', 'status', 'created_at'], 'production_scope_idx');

                $table->foreign('location_id', 'production_location_fk')
                    ->references('id')->on('locations')->restrictOnDelete();

                $table->foreign('recipe_id', 'production_recipe_fk')
                    ->references('id')->on('recipes')->restrictOnDelete();

                $table->foreign('product_id', 'production_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();

                $table->foreign('created_by', 'production_created_by_fk')
                    ->references('id')->on('users')->restrictOnDelete();

                $table->foreign('started_by', 'production_started_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->foreign('completed_by', 'production_completed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->foreign('cancelled_by', 'production_cancelled_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_order_items')) {
            Schema::create('production_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('production_order_id');
                $table->unsignedBigInteger('recipe_item_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('recipe_unit_id')->nullable();

                $table->decimal('recipe_quantity', 14, 6);
                $table->decimal('conversion_to_stock_unit', 16, 8)->default(1);
                $table->decimal('waste_percent_snapshot', 8, 4)->default(0);

                $table->decimal('planned_stock_quantity', 14, 6);
                $table->decimal('issued_stock_quantity', 14, 6)->default(0);
                $table->decimal('actual_stock_quantity', 14, 6)->nullable();

                $table->decimal('planned_unit_cost', 16, 6)->default(0);
                $table->decimal('planned_total_cost', 16, 4)->default(0);
                $table->decimal('issued_unit_cost', 16, 6)->nullable();
                $table->decimal('actual_total_cost', 16, 4)->nullable();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['production_order_id', 'product_id'], 'production_item_product_uq');

                $table->foreign('production_order_id', 'production_item_order_fk')
                    ->references('id')->on('production_orders')->cascadeOnDelete();

                $table->foreign('recipe_item_id', 'production_item_recipe_item_fk')
                    ->references('id')->on('recipe_items')->nullOnDelete();

                $table->foreign('product_id', 'production_item_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();

                $table->foreign('recipe_unit_id', 'production_item_unit_fk')
                    ->references('id')->on('units')->nullOnDelete();
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
