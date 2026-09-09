<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modifier_groups')) {
            Schema::create('modifier_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 180);
                $table->string('name_ar', 180)->nullable();
                $table->string('selection_type', 20)->default('single');
                $table->unsignedTinyInteger('min_selections')->default(0);
                $table->unsignedTinyInteger('max_selections')->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['is_active', 'sort_order'], 'modifier_groups_active_sort_idx');
            });
        }

        if (! Schema::hasTable('modifiers')) {
            Schema::create('modifiers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('modifier_group_id')
                    ->constrained('modifier_groups')
                    ->cascadeOnDelete();
                $table->string('code', 100)->unique();
                $table->string('name', 180);
                $table->string('name_ar', 180)->nullable();
                $table->decimal('price_delta', 12, 3)->default(0);
                $table->boolean('allow_quantity')->default(false);
                $table->unsignedTinyInteger('max_quantity')->default(1);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('configuration')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(
                    ['modifier_group_id', 'is_active', 'sort_order'],
                    'modifiers_group_active_sort_idx'
                );
            });
        }

        // Compatibility with the earlier foundation draft: if the table
        // already exists, add the quantity-control columns safely.
        if (Schema::hasTable('modifiers')) {
            Schema::table('modifiers', function (Blueprint $table): void {
                if (! Schema::hasColumn('modifiers', 'allow_quantity')) {
                    $table->boolean('allow_quantity')->default(false)->after('price_delta');
                }

                if (! Schema::hasColumn('modifiers', 'max_quantity')) {
                    $table->unsignedTinyInteger('max_quantity')->default(1)->after('allow_quantity');
                }
            });
        }

        if (! Schema::hasTable('product_modifier_groups')) {
            Schema::create('product_modifier_groups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')
                    ->constrained('products')
                    ->cascadeOnDelete();
                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->constrained('product_variants')
                    ->nullOnDelete();
                $table->foreignId('modifier_group_id')
                    ->constrained('modifier_groups')
                    ->cascadeOnDelete();
                $table->boolean('is_required_override')->nullable();
                $table->unsignedTinyInteger('min_selections_override')->nullable();
                $table->unsignedTinyInteger('max_selections_override')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(
                    ['product_id', 'product_variant_id', 'is_active'],
                    'product_modifier_groups_product_variant_idx'
                );
                $table->index(
                    ['modifier_group_id', 'is_active'],
                    'product_modifier_groups_group_active_idx'
                );
            });
        }

        if (! Schema::hasTable('modifier_ingredient_adjustments')) {
            Schema::create('modifier_ingredient_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('modifier_id')
                    ->constrained('modifiers')
                    ->cascadeOnDelete();
                $table->foreignId('ingredient_product_id')
                    ->constrained('products')
                    ->restrictOnDelete();
                $table->foreignId('unit_id')
                    ->nullable()
                    ->constrained('units')
                    ->nullOnDelete();
                // Signed: + consumes more, - removes consumption from base recipe.
                $table->decimal('quantity_delta', 14, 6);
                $table->decimal('conversion_to_stock_unit', 14, 6)->default(1);
                $table->string('unit_snapshot', 80)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(
                    ['modifier_id', 'ingredient_product_id'],
                    'modifier_adjustments_modifier_product_idx'
                );
            });
        }

        if (! Schema::hasTable('order_item_modifiers')) {
            Schema::create('order_item_modifiers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_item_id')
                    ->constrained('order_items')
                    ->cascadeOnDelete();
                $table->foreignId('modifier_group_id')
                    ->nullable()
                    ->constrained('modifier_groups')
                    ->nullOnDelete();
                $table->foreignId('modifier_id')
                    ->nullable()
                    ->constrained('modifiers')
                    ->nullOnDelete();
                $table->string('group_name_snapshot', 180)->nullable();
                $table->string('modifier_name_snapshot', 180);
                $table->decimal('price_delta_snapshot', 12, 3)->default(0);
                // Quantity of this modifier per one sold item.
                $table->unsignedTinyInteger('quantity')->default(1);
                // Full delta for this order line at creation time.
                $table->decimal('total_delta_snapshot', 14, 3)->default(0);
                $table->decimal('ingredient_cost_delta_snapshot', 14, 4)->nullable();
                $table->json('configuration_snapshot')->nullable();
                $table->timestamps();
                $table->index(
                    ['order_item_id', 'modifier_group_id'],
                    'order_item_modifiers_item_group_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('modifier_ingredient_adjustments');
        Schema::dropIfExists('product_modifier_groups');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('modifier_groups');
    }
};
