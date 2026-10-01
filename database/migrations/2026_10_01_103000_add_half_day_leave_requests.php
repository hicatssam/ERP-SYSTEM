<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leave_requests', function (Blueprint $table): void {
            $table->decimal('day_fraction', 3, 2)->default(1);
            $table->string('half_day_slot', 20)->nullable();
            $table->boolean('is_paid_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_requests', fn (Blueprint $table) =>
            $table->dropColumn(['day_fraction', 'half_day_slot', 'is_paid_snapshot']));
    }
};
