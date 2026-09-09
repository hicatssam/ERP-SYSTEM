<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_menu_items')) {
            return;
        }

        if (! Schema::hasColumn('restaurant_menu_items', 'inventory_mode')) {
            Schema::table('restaurant_menu_items', function (Blueprint $table): void {
                $table->string('inventory_mode', 20)
                    ->default('auto')
                    ->after('show_in_delivery');

                $table->index('inventory_mode');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('restaurant_menu_items')
            && Schema::hasColumn('restaurant_menu_items', 'inventory_mode')
        ) {
            Schema::table('restaurant_menu_items', function (Blueprint $table): void {
                $table->dropIndex(['inventory_mode']);
                $table->dropColumn('inventory_mode');
            });
        }
    }
};
