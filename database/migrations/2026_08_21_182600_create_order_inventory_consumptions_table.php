<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_inventory_consumptions')) {
            return;
        }

        Schema::create('order_inventory_consumptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->foreignId('location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table->foreignId('sold_product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('stock_product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('recipe_id')
                ->nullable()
                ->constrained('recipes')
                ->nullOnDelete();

            $table->foreignId('recipe_item_id')
                ->nullable()
                ->constrained('recipe_items')
                ->nullOnDelete();

            $table->string('source', 20);          // product | recipe
            $table->string('inventory_mode', 20); // product | recipe | auto

            $table->decimal('order_quantity', 14, 3);
            $table->decimal('recipe_yield_quantity', 14, 4)->nullable();
            $table->decimal('quantity_per_yield', 14, 4)->nullable();
            $table->decimal('waste_percent', 8, 3)->default(0);
            $table->decimal('consumed_quantity', 14, 3);

            $table->decimal('unit_cost_snapshot', 14, 4)->nullable();
            $table->decimal('total_cost_snapshot', 16, 4)->nullable();

            $table->unsignedInteger('revision')->default(0);
            $table->timestamp('restored_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['order_item_id', 'stock_product_id'],
                'order_inventory_consumption_item_stock_uq'
            );

            $table->index(['order_id', 'restored_at']);
            $table->index(['location_id', 'stock_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_inventory_consumptions');
    }
};
