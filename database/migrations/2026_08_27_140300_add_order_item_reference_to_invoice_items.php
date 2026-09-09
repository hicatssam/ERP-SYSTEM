<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_items', 'order_item_id')) {
                $table->foreignId('order_item_id')
                    ->nullable()
                    ->after('invoice_id')
                    ->constrained('order_items')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('invoice_items', 'order_item_id')) {
                $table->dropConstrainedForeignId('order_item_id');
            }
        });
    }
};
