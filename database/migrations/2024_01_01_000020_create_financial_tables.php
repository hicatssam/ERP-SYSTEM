<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index('status');
        });

        Schema::create('financial_period_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_period_id')->constrained('financial_periods')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('discounts', 12, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->decimal('confirmed_collections', 14, 2)->default(0);
            $table->decimal('refunds', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 14, 2)->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('average_order_value', 12, 2)->default(0);
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->default(0);
            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['financial_period_id', 'location_id']);
        });

        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_period_id')->constrained('financial_periods')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->enum('adjustment_type', ['increase', 'decrease', 'correction', 'carry_forward_adjustment']);
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('entry_date');
            $table->enum('entry_type', [
                'sale', 'sale_cancellation', 'discount', 'refund',
                'payment_collection', 'payment_reversal', 'adjustment',
                'opening_balance', 'closing_balance', 'carry_forward'
            ]);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('special_cake_order_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3)->default('ILS');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['location_id', 'entry_date']);
            $table->index('entry_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_ledger_entries');
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('financial_period_summaries');
        Schema::dropIfExists('financial_periods');
    }
};
