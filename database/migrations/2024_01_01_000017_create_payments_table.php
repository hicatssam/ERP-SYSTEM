<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->enum('order_type', ['order', 'special_cake_order']);
            $table->unsignedBigInteger('order_id');
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('status', [
                'pending_verification', 'confirmed', 'rejected', 'corrected', 'refunded'
            ])->default('confirmed');
            $table->string('reference_number', 100)->nullable();
            $table->string('payment_proof')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['order_type', 'order_id']);
            $table->index('status');
        });

        Schema::create('payment_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_payment_id')->constrained('payments')->restrictOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('corrected_amount', 12, 2);
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->enum('order_type', ['order', 'special_cake_order']);
            $table->unsignedBigInteger('order_id');
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['order_type', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_corrections');
        Schema::dropIfExists('payments');
    }
};
