<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('report_type');
            // 'daily' | 'weekly' | 'monthly'
            $table->string('frequency');
            // For weekly: 0=Sunday … 6=Saturday; null for daily/monthly
            $table->unsignedTinyInteger('day_of_week')->nullable();
            // Hour (0–23) at which the report is sent
            $table->unsignedTinyInteger('hour')->default(8);
            // Comma-separated recipient email addresses
            $table->text('recipients');
            // Optional location filter; null = all locations
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            // 'last_7_days' | 'last_30_days' | 'this_month' | 'last_month' | 'today' | 'yesterday'
            $table->string('date_range')->default('last_7_days');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
