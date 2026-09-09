<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Compatibility migration for anyone who already ran the first draft
         * of Cafe Sprint 1 before the project-level integration review.
         */
        if (Schema::hasTable('order_items')) {
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
        }

        if (Schema::hasTable('recipes')) {
            Schema::table('recipes', function (Blueprint $table): void {
                if (! Schema::hasColumn('recipes', 'product_variant_id')) {
                    $table->foreignId('product_variant_id')
                        ->nullable()
                        ->after('product_id')
                        ->constrained('product_variants')
                        ->nullOnDelete();
                }
            });
        }

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
    }

    public function down(): void
    {
        // Intentionally non-destructive: this migration only normalizes an
        // already-installed foundation draft and must never remove its data.
    }
};
