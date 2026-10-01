<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('locations')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name', 190);
            $table->timestamps();
            $table->index(['holiday_date', 'location_id']);
        });

        Schema::table('leave_types', function (Blueprint $table): void {
            $table->string('count_basis', 20)->default('calendar');
        });

        Schema::table('employee_leave_requests', function (Blueprint $table): void {
            $table->json('yearly_days')->nullable();
            $table->json('countable_dates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_requests', fn (Blueprint $table) => $table->dropColumn(['yearly_days', 'countable_dates']));
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropColumn('count_basis'));
        Schema::dropIfExists('work_holidays');
    }
};
