<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advance_repayments', function (Blueprint $table): void {
            $table->id();
            $table->string('document_number', 80)->unique();

            $table->foreignId('employee_advance_id')
                ->constrained('employee_advances')
                ->restrictOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            $table->decimal('amount', 14, 4);

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();

            $table->string('status', 30)->default('posted');
            $table->dateTime('paid_at');
            $table->string('reference', 120)->nullable();
            $table->string('payment_proof')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('verified_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();

            $table->timestamps();

            $table->index(
                ['employee_id', 'paid_at'],
                'employee_advance_repayments_employee_date_idx'
            );

            $table->index(
                ['employee_advance_id', 'status'],
                'employee_advance_repayments_advance_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advance_repayments');
    }
};
