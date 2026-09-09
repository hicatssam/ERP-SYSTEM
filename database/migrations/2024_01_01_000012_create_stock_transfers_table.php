<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('stock_request_id')->nullable()->constrained('stock_requests')->nullOnDelete();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->enum('status', ['draft', 'dispatched', 'received', 'discrepancy_open', 'resolved'])->default('draft');
            $table->text('dispatch_notes')->nullable();
            $table->text('receiving_notes')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('status');
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('sent_quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3)->nullable();
            $table->decimal('damaged_quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'product_id']);
        });

        Schema::create('transfer_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('sent_quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3);
            $table->decimal('variance', 12, 3);
            $table->enum('discrepancy_type', ['shortage', 'damage', 'overage', 'other']);
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_discrepancies');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
