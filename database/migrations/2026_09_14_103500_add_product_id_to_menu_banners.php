<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('menu_banners', 'product_id')) {
            Schema::table('menu_banners', function (Blueprint $table): void {
                $table->foreignId('product_id')
                    ->nullable()
                    ->after('location_id')
                    ->constrained('products')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_banners', 'product_id')) {
            Schema::table('menu_banners', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('product_id');
            });
        }
    }
};
