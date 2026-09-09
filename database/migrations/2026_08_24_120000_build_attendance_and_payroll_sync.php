<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 190);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->unsignedInteger('overtime_after_minutes')->default(0);
            $table->json('work_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from', 'effective_to'], 'esa_employee_dates_idx');
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->date('work_date');
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('status', 24)->default('present');
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('source', 30)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date'], 'attendance_employee_date_unique');
            $table->index(['work_date', 'status']);
        });

        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 190);
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_days', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'start_date'], 'elr_employee_status_date_idx');
        });

        if (Schema::hasTable('employee_payroll_adjustments')) {
            Schema::table('employee_payroll_adjustments', function (Blueprint $table): void {
                if (! Schema::hasColumn('employee_payroll_adjustments', 'source_type')) {
                    $table->string('source_type', 60)->nullable()->after('notes');
                }
                if (! Schema::hasColumn('employee_payroll_adjustments', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                }
                if (! Schema::hasColumn('employee_payroll_adjustments', 'metadata')) {
                    $table->json('metadata')->nullable()->after('source_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_payroll_adjustments')) {
            Schema::table('employee_payroll_adjustments', function (Blueprint $table): void {
                $drop = [];
                foreach (['source_type', 'source_id', 'metadata'] as $column) {
                    if (Schema::hasColumn('employee_payroll_adjustments', $column)) {
                        $drop[] = $column;
                    }
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        Schema::dropIfExists('employee_leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employee_shift_assignments');
        Schema::dropIfExists('work_shifts');
    }
};
