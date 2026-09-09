<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 100);
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal');
            $table->json('payload');
            $table->string('idempotency_key', 100)->unique();
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->enum('processing_status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            $table->unsignedTinyInteger('processing_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('processing_status');
            $table->index('event_key');
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('event_key', 100);
            $table->enum('audience_type', ['internal', 'customer']);
            $table->enum('channel', ['database', 'broadcast', 'email', 'sms', 'whatsapp']);
            $table->string('locale', 5)->default('ar');
            $table->string('title_template')->nullable();
            $table->text('body_template');
            $table->json('variables_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('event_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('system_events');
    }
};
