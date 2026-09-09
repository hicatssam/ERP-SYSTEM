<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->enum('movement_type', ['in', 'out', 'adjustment']);
            $table->enum('reason', [
                'factory_production', 'stock_received', 'order', 'damaged',
                'expired', 'return', 'internal_use', 'correction', 'opening_stock',
                'stock_count_adjustment', 'transfer_dispatch', 'transfer_receipt'
            ]);
            $table->decimal('quantity', 12, 3);
            $table->decimal('balance_before', 12, 3)->default(0);
            $table->decimal('balance_after', 12, 3)->default(0);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['location_id', 'product_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
