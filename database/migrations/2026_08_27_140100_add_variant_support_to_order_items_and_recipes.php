<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_variants')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('order_items', 'variant_name_snapshot')) {
                $table->string('variant_name_snapshot', 180)
                    ->nullable()
                    ->after('product_name');
            }
        });

        Schema::table('recipes', function (Blueprint $table): void {
            if (! Schema::hasColumn('recipes', 'product_variant_id')) {
                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_variants')
                    ->nullOnDelete();
            }
        });

        /*
         * Recipe version numbers intentionally remain globally unique per
         * product using the existing (product_id, version) constraint.
         * This avoids nullable-UNIQUE inconsistencies between MySQL/SQLite.
         * Variant-specific recipes are scoped by product_variant_id for
         * activation, while version numbering stays monotonic per product.
         */
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            if (Schema::hasColumn('recipes', 'product_variant_id')) {
                $table->dropConstrainedForeignId('product_variant_id');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->dropConstrainedForeignId('product_variant_id');
            }

            if (Schema::hasColumn('order_items', 'variant_name_snapshot')) {
                $table->dropColumn('variant_name_snapshot');
            }
        });
    }
};
