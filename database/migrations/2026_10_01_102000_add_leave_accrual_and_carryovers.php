<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table): void {
            $table->string('accrual_mode', 20)->default('annual');
            $table->decimal('carryover_limit_days', 8, 2)->default(0);
        });

        Schema::create('employee_leave_carryovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 8, 2);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_carryover_employee_type_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_carryovers');
        Schema::table('leave_types', fn (Blueprint $table) =>
            $table->dropColumn(['accrual_mode', 'carryover_limit_days']));
    }
};
