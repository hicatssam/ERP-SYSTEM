<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('id')
                ->constrained('locations')
                ->restrictOnDelete();
        });

        // Existing menu rows came from the first version where menu definition was global.
        // Assign each one to the first branch/location already linked to its Product.
        DB::table('restaurant_menu_items')
            ->orderBy('id')
            ->get()
            ->each(function ($menuItem): void {
                $locationId = DB::table('location_products')
                    ->where('product_id', $menuItem->product_id)
                    ->orderByDesc('is_available')
                    ->orderBy('id')
                    ->value('location_id');

                if ($locationId) {
                    DB::table('restaurant_menu_items')
                        ->where('id', $menuItem->id)
                        ->update(['location_id' => $locationId]);
                }
            });

        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            $table->dropUnique('restaurant_menu_items_product_id_unique');
            $table->unique(
                ['location_id', 'product_id'],
                'restaurant_menu_location_product_unique'
            );
            $table->index(
                ['location_id', 'is_active', 'show_in_pos'],
                'restaurant_menu_pos_scope_idx'
            );
        });
    }

    public function down(): void
    {
        // Keep one row per product before restoring the old global unique rule.
        DB::table('restaurant_menu_items')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id')
            ->each(function ($rows): void {
                $rows->slice(1)->each(function ($row): void {
                    DB::table('restaurant_menu_items')->where('id', $row->id)->delete();
                });
            });

        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            $table->dropIndex('restaurant_menu_pos_scope_idx');
            $table->dropUnique('restaurant_menu_location_product_unique');
            $table->dropConstrainedForeignId('location_id');
            $table->unique('product_id');
        });
    }
};
