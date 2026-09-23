<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_bank_transfers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();

            $table->foreignId('location_payment_account_id')
                ->nullable()
                ->constrained('location_payment_accounts')
                ->nullOnDelete();

            $table->string('sender_name', 150);
            $table->string('sender_phone', 50)->nullable();
            $table->string('sender_account_number', 120)->nullable();
            $table->string('reference_number', 120);
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('ILS');

            $table->string('status', 30)->default('pending_verification');
            $table->string('payment_proof')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('received_at')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['location_id', 'status', 'received_at'], 'incoming_transfer_location_status_date_idx');
            $table->index(['payment_method_id', 'status'], 'incoming_transfer_method_status_idx');
            $table->index(['location_payment_account_id', 'received_at'], 'incoming_transfer_account_date_idx');
            $table->index(['reference_number'], 'incoming_transfer_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_bank_transfers');
    }
};
