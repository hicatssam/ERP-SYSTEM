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
                $table->string('code', 80)->unique();
                $table->unsignedBigInteger('product_id');
                $table->string('name', 180);
                $table->unsignedInteger('version')->default(1);
                $table->decimal('yield_quantity', 14, 3)->default(1);
                $table->string('status', 30)->default('draft');
                $table->boolean('is_active')->default(false);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['product_id', 'version'],
                    'recipes_product_version_uq'
                );
                $table->index(
                    ['product_id', 'status', 'is_active'],
                    'recipes_product_status_idx'
                );

                $table->foreign('product_id', 'recipes_product_fk')
                    ->references('id')->on('products')
                    ->restrictOnDelete();

                $table->foreign('created_by', 'recipes_creator_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->foreign('approved_by', 'recipes_approver_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('ingredient_product_id');
                $table->decimal('quantity', 14, 3);
                $table->decimal('expected_waste_percent', 7, 3)->default(0);
                $table->string('unit_snapshot', 60)->nullable();
                $table->string('stage', 120)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(
                    ['recipe_id', 'sort_order'],
                    'recipe_items_recipe_sort_idx'
                );
                $table->index(
                    ['ingredient_product_id', 'recipe_id'],
                    'recipe_items_ingredient_idx'
                );

                $table->foreign('recipe_id', 'recipe_items_recipe_fk')
                    ->references('id')->on('recipes')
                    ->cascadeOnDelete();

                $table->foreign('ingredient_product_id', 'recipe_items_product_fk')
                    ->references('id')->on('products')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('production_batches')) {
            Schema::create('production_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_number', 80)->nullable()->unique();
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('location_id');

                $table->unsignedInteger('recipe_version');
                $table->json('recipe_snapshot')->nullable();

                $table->string('status', 40)->default('draft');
                $table->decimal('planned_output_quantity', 14, 3);
                $table->decimal('actual_output_quantity', 14, 3)->nullable();
                $table->decimal('accepted_output_quantity', 14, 3)->default(0);
                $table->decimal('rejected_output_quantity', 14, 3)->default(0);

                $table->decimal('standard_material_cost', 16, 4)->default(0);
                $table->decimal('actual_material_cost', 16, 4)->default(0);
                $table->decimal('actual_unit_cost', 16, 4)->default(0);

                $table->boolean('quality_required')->default(false);
                $table->date('output_expiry_date')->nullable();

                $table->date('planned_date')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('materials_issued_at')->nullable();
                $table->timestamp('submitted_for_quality_at')->nullable();
                $table->timestamp('output_posted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('rejected_at')->nullable();

                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('released_by')->nullable();
                $table->unsignedBigInteger('started_by')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();

                $table->text('notes')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamps();

                $table->index(
                    ['location_id', 'status', 'planned_date'],
                    'prod_batches_location_status_idx'
                );
                $table->index(
                    ['product_id', 'created_at'],
                    'prod_batches_product_date_idx'
                );

                $table->foreign('recipe_id', 'prod_batches_recipe_fk')
                    ->references('id')->on('recipes')
                    ->restrictOnDelete();

                $table->foreign('product_id', 'prod_batches_product_fk')
                    ->references('id')->on('products')
                    ->restrictOnDelete();

                $table->foreign('location_id', 'prod_batches_location_fk')
                    ->references('id')->on('locations')
                    ->restrictOnDelete();

                $table->foreign('created_by', 'prod_batches_creator_fk')
                    ->references('id')->on('users')
                    ->restrictOnDelete();

                $table->foreign('released_by', 'prod_batches_releaser_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->foreign('started_by', 'prod_batches_starter_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->foreign('completed_by', 'prod_batches_completer_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->foreign('cancelled_by', 'prod_batches_canceller_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_batch_items')) {
            Schema::create('production_batch_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('production_batch_id');
                $table->unsignedBigInteger('recipe_item_id')->nullable();
                $table->unsignedBigInteger('ingredient_product_id');

                $table->string('ingredient_name_snapshot', 180);
                $table->string('unit_snapshot', 60)->nullable();
                $table->string('stage_snapshot', 120)->nullable();

                $table->decimal('planned_quantity', 14, 3);
                $table->decimal('issued_quantity', 14, 3)->default(0);
                $table->decimal('actual_consumed_quantity', 14, 3)->nullable();
                $table->decimal('waste_quantity', 14, 3)->default(0);

                $table->decimal('unit_cost_snapshot', 16, 4)->default(0);
                $table->decimal('actual_cost', 16, 4)->default(0);

                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(
                    ['production_batch_id', 'sort_order'],
                    'prod_batch_items_batch_sort_idx'
                );
                $table->index(
                    ['ingredient_product_id', 'production_batch_id'],
                    'prod_batch_items_product_idx'
                );

                $table->foreign('production_batch_id', 'prod_batch_items_batch_fk')
                    ->references('id')->on('production_batches')
                    ->cascadeOnDelete();

                $table->foreign('recipe_item_id', 'prod_batch_items_recipe_item_fk')
                    ->references('id')->on('recipe_items')
                    ->nullOnDelete();

                $table->foreign('ingredient_product_id', 'prod_batch_items_product_fk')
                    ->references('id')->on('products')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('production_material_allocations')) {
            Schema::create('production_material_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('production_batch_item_id');
                $table->unsignedBigInteger('inventory_batch_id')->nullable();
                $table->decimal('quantity_issued', 14, 3);
                $table->decimal('quantity_returned', 14, 3)->default(0);
                $table->decimal('unit_cost', 16, 4)->default(0);
                $table->timestamps();

                $table->index(
                    ['production_batch_item_id', 'inventory_batch_id'],
                    'prod_alloc_item_batch_idx'
                );

                $table->foreign('production_batch_item_id', 'prod_alloc_item_fk')
                    ->references('id')->on('production_batch_items')
                    ->cascadeOnDelete();

                $table->foreign('inventory_batch_id', 'prod_alloc_inventory_batch_fk')
                    ->references('id')->on('inventory_batches')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_quality_checks')) {
            Schema::create('production_quality_checks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('production_batch_id');
                $table->string('status', 30)->default('pending');
                $table->decimal('accepted_quantity', 14, 3)->default(0);
                $table->decimal('rejected_quantity', 14, 3)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('checked_by')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();

                $table->unique(
                    'production_batch_id',
                    'prod_quality_batch_uq'
                );

                $table->foreign('production_batch_id', 'prod_quality_batch_fk')
                    ->references('id')->on('production_batches')
                    ->cascadeOnDelete();

                $table->foreign('checked_by', 'prod_quality_checker_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_quality_check_items')) {
            Schema::create('production_quality_check_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('production_quality_check_id');
                $table->string('criterion', 180);
                $table->string('result', 30);
                $table->text('notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(
                    ['production_quality_check_id', 'sort_order'],
                    'prod_quality_items_check_sort_idx'
                );

                $table->foreign('production_quality_check_id', 'prod_quality_items_check_fk')
                    ->references('id')->on('production_quality_checks')
                    ->cascadeOnDelete();
            });
        }

        if (
            Schema::hasTable('inventory_batches')
            && ! Schema::hasColumn('inventory_batches', 'production_batch_id')
        ) {
            Schema::table('inventory_batches', function (Blueprint $table) {
                $table->unsignedBigInteger('production_batch_id')->nullable();

                $table->unique(
                    'production_batch_id',
                    'inv_batches_production_uq'
                );

                $table->foreign('production_batch_id', 'inv_batches_production_fk')
                    ->references('id')->on('production_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('inventory_batches')
            && Schema::hasColumn('inventory_batches', 'production_batch_id')
        ) {
            Schema::table('inventory_batches', function (Blueprint $table) {
                $table->dropForeign('inv_batches_production_fk');
                $table->dropUnique('inv_batches_production_uq');
                $table->dropColumn('production_batch_id');
            });
        }

        Schema::dropIfExists('production_quality_check_items');
        Schema::dropIfExists('production_quality_checks');
        Schema::dropIfExists('production_material_allocations');
        Schema::dropIfExists('production_batch_items');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
