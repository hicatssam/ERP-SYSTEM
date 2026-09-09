<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_expiry_alerts')) return;

        Schema::create('inventory_expiry_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('alert_key', 190)->unique();
            $table->string('alert_type', 40);
            $table->integer('threshold_days')->nullable();
            $table->string('batch_number', 120)->nullable();
            $table->date('expiry_date');
            $table->decimal('available_quantity', 14, 3)->default(0);
            $table->integer('days_left')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('first_detected_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('database_sent_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->json('delivery_errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'expiry_date']);
            $table->index(['location_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_expiry_alerts');
    }
};
