<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_menu_items', function (Blueprint $table) {
            $table->id();

            // The real catalog / inventory product that will be sold.
            // Orders continue storing product_id, so inventory/accounting workflows stay intact.
            $table->foreignId('product_id')
                ->unique()
                ->constrained('products')
                ->restrictOnDelete();

            // Sales-facing overrides. Null means: use the Product value.
            $table->string('display_name')->nullable();
            $table->string('display_name_ar')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_pos')->default(true);
            $table->boolean('show_in_qr')->default(true);
            $table->boolean('show_in_delivery')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'show_in_pos']);
            $table->index(['is_active', 'show_in_qr']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_items');
    }
};
