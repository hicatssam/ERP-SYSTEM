<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_proof_analyses')) {
            return;
        }

        Schema::create('payment_proof_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('proof_sha256', 64)->nullable()->index();
            $table->string('sender_name')->nullable();
            $table->string('sender_account')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_account')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->decimal('extracted_amount', 14, 2)->nullable();
            $table->string('extracted_currency', 12)->nullable();
            $table->dateTime('transaction_at')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('risk_level', 20)->default('unknown');
            $table->json('risk_signals')->nullable();
            $table->text('raw_text')->nullable();
            $table->json('raw_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proof_analyses');
    }
};
