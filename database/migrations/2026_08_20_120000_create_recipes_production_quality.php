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
                $table->unsignedBigInteger('product_id');
                $table->string('name', 160);
                $table->unsignedInteger('version')->default(1);
                $table->string('status', 20)->default('draft');
                $table->decimal('yield_quantity', 14, 3)->default(1);
                $table->decimal('labor_cost_per_batch', 14, 2)->default(0);
                $table->decimal('overhead_percent', 8, 3)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('activated_by')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();

                $table->foreign('product_id', 'rcp_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();
                $table->foreign('created_by', 'rcp_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('activated_by', 'rcp_activated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique(['product_id', 'version'], 'rcp_product_version_uq');
                $table->index(['product_id', 'status'], 'rcp_product_status_idx');
            });
        }

        if (! Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('ingredient_product_id');
                $table->decimal('quantity', 14, 4);
                $table->decimal('waste_percent', 8, 3)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('recipe_id', 'rci_recipe_fk')
                    ->references('id')->on('recipes')->cascadeOnDelete();
                $table->foreign('ingredient_product_id', 'rci_ingredient_fk')
                    ->references('id')->on('products')->restrictOnDelete();

                $table->unique(['recipe_id', 'ingredient_product_id'], 'rci_recipe_ingredient_uq');
                $table->index(['ingredient_product_id', 'recipe_id'], 'rci_ingredient_recipe_idx');
            });
        }

        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table): void {
                $table->id();
                $table->string('production_number', 70)->nullable();
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('product_id');
                $table->string('status', 30)->default('draft');
                $table->boolean('quality_required')->default(false);

                $table->decimal('planned_output_quantity', 14, 3);
                $table->decimal('actual_output_quantity', 14, 3)->nullable();
                $table->decimal('output_variance_quantity', 14, 3)->nullable();

                $table->decimal('planned_material_cost', 16, 2)->default(0);
                $table->decimal('planned_labor_cost', 16, 2)->default(0);
                $table->decimal('planned_overhead_cost', 16, 2)->default(0);
                $table->decimal('planned_total_cost', 16, 2)->default(0);
                $table->decimal('planned_unit_cost', 16, 4)->default(0);
                $table->decimal('actual_material_cost', 16, 2)->default(0);
                $table->decimal('actual_labor_cost', 16, 2)->default(0);
                $table->decimal('actual_overhead_cost', 16, 2)->default(0);
                $table->decimal('actual_total_cost', 16, 2)->default(0);
                $table->decimal('actual_unit_cost', 16, 4)->default(0);

                $table->timestamp('planned_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('materials_consumed_at')->nullable();
                $table->timestamp('submitted_quality_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('rejected_at')->nullable();

                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('released_by')->nullable();
                $table->unsignedBigInteger('started_by')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('location_id', 'prd_location_fk')
                    ->references('id')->on('locations')->restrictOnDelete();
                $table->foreign('recipe_id', 'prd_recipe_fk')
                    ->references('id')->on('recipes')->restrictOnDelete();
                $table->foreign('product_id', 'prd_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();
                $table->foreign('created_by', 'prd_created_by_fk')
                    ->references('id')->on('users')->restrictOnDelete();
                $table->foreign('released_by', 'prd_released_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('started_by', 'prd_started_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('completed_by', 'prd_completed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('cancelled_by', 'prd_cancelled_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique('production_number', 'prd_number_uq');
                $table->index(['location_id', 'status', 'created_at'], 'prd_location_status_idx');
                $table->index(['product_id', 'created_at'], 'prd_product_date_idx');
            });
        }

        if (! Schema::hasTable('production_order_items')) {
            Schema::create('production_order_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('production_order_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('planned_quantity', 14, 4);
                $table->decimal('reserved_quantity', 14, 4)->default(0);
                $table->decimal('actual_quantity', 14, 4)->nullable();
                $table->decimal('waste_percent', 8, 3)->default(0);
                $table->decimal('planned_unit_cost', 16, 4)->default(0);
                $table->decimal('planned_cost', 16, 2)->default(0);
                $table->decimal('actual_unit_cost', 16, 4)->default(0);
                $table->decimal('actual_cost', 16, 2)->default(0);
                $table->timestamp('consumed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('production_order_id', 'poi_order_fk')
                    ->references('id')->on('production_orders')->cascadeOnDelete();
                $table->foreign('product_id', 'poi_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();

                $table->unique(['production_order_id', 'product_id'], 'poi_order_product_uq');
                $table->index(['product_id', 'production_order_id'], 'poi_product_order_idx');
            });
        }

        if (! Schema::hasTable('production_quality_inspections')) {
            Schema::create('production_quality_inspections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('production_order_id');
                $table->string('status', 20);
                $table->json('measurements')->nullable();
                $table->text('notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('inspected_by');
                $table->timestamp('inspected_at');
                $table->timestamps();

                $table->foreign('production_order_id', 'pqi_order_fk')
                    ->references('id')->on('production_orders')->cascadeOnDelete();
                $table->foreign('inspected_by', 'pqi_inspector_fk')
                    ->references('id')->on('users')->restrictOnDelete();

                $table->unique('production_order_id', 'pqi_order_uq');
                $table->index(['status', 'inspected_at'], 'pqi_status_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_quality_inspections');
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
