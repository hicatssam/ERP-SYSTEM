<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 190);
            $table->string('vendor', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('connection_mode', 30)->default('local_bridge');
            $table->string('ip_address', 64)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('base_url', 500)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->char('api_token_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('settings')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['location_id', 'is_active']);
            $table->index(['vendor', 'model']);
        });

        Schema::create('employee_biometric_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('device_user_id', 120);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['attendance_device_id', 'device_user_id'], 'ebm_device_user_unique');
            $table->unique(['attendance_device_id', 'employee_id'], 'ebm_device_employee_unique');
        });

        Schema::create('attendance_punches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignId('employee_biometric_mapping_id')->nullable()->constrained('employee_biometric_mappings')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('device_user_id', 120);
            $table->string('external_id', 190)->nullable();
            $table->dateTime('punch_at');
            $table->string('punch_type', 30)->default('unknown');
            $table->date('work_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->char('fingerprint', 64)->unique();
            $table->json('raw_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['attendance_device_id', 'punch_at'], 'ap_device_date_idx');
            $table->index(['employee_id', 'work_date', 'punch_at'], 'ap_employee_workdate_idx');
            $table->index(['status', 'punch_at']);
        });

        Schema::create('attendance_device_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->string('direction', 20)->default('push');
            $table->string('status', 20);
            $table->unsignedInteger('received_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['attendance_device_id', 'started_at'], 'adsl_device_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_sync_logs');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('employee_biometric_mappings');
        Schema::dropIfExists('attendance_devices');
    }
};
