<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->foreignId('branch_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('factory_location_id')->constrained('locations')->restrictOnDelete();
            $table->enum('status', [
                'pending_factory_review', 'accepted', 'partially_accepted',
                'rejected', 'preparing', 'dispatched', 'received',
                'discrepancy_open', 'resolved'
            ])->default('pending_factory_review');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('branch_location_id');
            $table->index('status');
        });

        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_id')->constrained('stock_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('requested_quantity', 12, 3);
            $table->decimal('approved_quantity', 12, 3)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['stock_request_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }
};
