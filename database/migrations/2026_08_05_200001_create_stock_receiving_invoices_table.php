<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receiving_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('receiving_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('sending_location_id')->constrained('locations')->restrictOnDelete();
            $table->integer('total_items_ordered')->default(0);
            $table->integer('total_items_received')->default(0);
            $table->integer('total_items_damaged')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index('stock_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receiving_invoices');
    }
};
