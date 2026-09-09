<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('salary_basis', 20)->default('monthly');
            $table->decimal('base_salary', 18, 4)->default(0);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'is_active', 'effective_from'], 'ecp_employee_active_idx');
        });

        Schema::create('employee_payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->nullable();
            $table->string('kind', 20);
            $table->string('name', 190);
            $table->decimal('amount', 18, 4);
            $table->boolean('is_recurring')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'kind', 'status'], 'epa_employee_kind_status_idx');
        });

        Schema::create('employee_advances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->decimal('recovered_amount', 18, 4)->default(0);
            $table->decimal('outstanding_amount', 18, 4);
            $table->date('issued_at');
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'issued_at'], 'ea_employee_status_date_idx');
        });

        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 190);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date'], 'pp_status_dates_idx');
        });

        Schema::table('employee_payroll_adjustments', function (Blueprint $table): void {
            $table->foreign('payroll_period_id')
                ->references('id')
                ->on('payroll_periods')
                ->nullOnDelete();
        });

        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('compensation_profile_id')
                ->nullable()
                ->constrained('employee_compensation_profiles')
                ->nullOnDelete();

            $table->decimal('base_salary', 18, 4)->default(0);
            $table->decimal('allowances_total', 18, 4)->default(0);
            $table->decimal('bonuses_total', 18, 4)->default(0);
            $table->decimal('deductions_total', 18, 4)->default(0);
            $table->decimal('gross_salary', 18, 4)->default(0);
            $table->decimal('net_salary', 18, 4)->default(0);
            $table->decimal('payable_amount', 18, 4)->default(0);
            $table->string('status', 20)->default('calculated');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id'], 'pi_period_employee_unique');
            $table->index(['employee_id', 'status'], 'pi_employee_status_idx');
        });

        Schema::create('payroll_item_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('direction', 10);
            $table->string('label', 190);
            $table->decimal('amount', 18, 4);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payroll_item_id', 'direction'], 'pic_item_direction_idx');
            $table->index(['source_type', 'source_id'], 'pic_source_idx');
        });

        Schema::create('payroll_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->timestamp('paid_at');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'paid_at'], 'ppay_employee_date_idx');
        });

        Schema::create('employee_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('direction', 10);
            $table->string('entry_type', 40);
            $table->decimal('amount', 18, 4);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained('payroll_items')->nullOnDelete();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 255);
            $table->string('reference', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'entry_date', 'id'], 'ele_employee_date_idx');
            $table->index(['source_type', 'source_id'], 'ele_source_idx');
            $table->index(['payroll_period_id', 'payroll_item_id'], 'ele_payroll_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ledger_entries');
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_item_components');
        Schema::dropIfExists('payroll_items');

        Schema::table('employee_payroll_adjustments', function (Blueprint $table): void {
            $table->dropForeign(['payroll_period_id']);
        });

        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('employee_payroll_adjustments');
        Schema::dropIfExists('employee_compensation_profiles');
    }
};
