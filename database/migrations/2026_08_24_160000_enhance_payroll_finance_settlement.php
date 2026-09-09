<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->string('document_number', 80)
                ->nullable()
                ->unique()
                ->after('id');

            $table->foreignId('financial_period_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('financial_periods')
                ->nullOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->after('financial_period_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->after('location_id')
                ->constrained('currencies')
                ->nullOnDelete();

            $table->decimal('exchange_rate', 18, 8)
                ->default(1)
                ->after('amount');

            $table->decimal('base_amount', 18, 4)
                ->default(0)
                ->after('exchange_rate');

            $table->string('status', 30)
                ->default('posted')
                ->after('base_amount');

            $table->string('payment_proof')
                ->nullable()
                ->after('reference');

            $table->foreignId('verified_by')
                ->nullable()
                ->after('notes')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')
                ->nullable()
                ->after('verified_by');

            $table->text('rejection_reason')
                ->nullable()
                ->after('verified_at');

            $table->foreignId('voided_by')
                ->nullable()
                ->after('rejection_reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('voided_at')
                ->nullable()
                ->after('voided_by');

            $table->text('void_reason')
                ->nullable()
                ->after('voided_at');

            $table->index(
                ['financial_period_id', 'status'],
                'payroll_payments_fin_period_status_idx'
            );

            $table->index(
                ['location_id', 'paid_at'],
                'payroll_payments_location_paid_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payroll_payments', function (Blueprint $table): void {
            $table->dropIndex('payroll_payments_fin_period_status_idx');
            $table->dropIndex('payroll_payments_location_paid_idx');

            $table->dropForeign(['financial_period_id']);
            $table->dropForeign(['location_id']);
            $table->dropForeign(['currency_id']);
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['voided_by']);

            $table->dropColumn([
                'document_number',
                'financial_period_id',
                'location_id',
                'currency_id',
                'exchange_rate',
                'base_amount',
                'status',
                'payment_proof',
                'verified_by',
                'verified_at',
                'rejection_reason',
                'voided_by',
                'voided_at',
                'void_reason',
            ]);
        });
    }
};
