<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_expiry_scan_runs')) {
            Schema::create('inventory_expiry_scan_runs', function (Blueprint $table): void {
                $table->id();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->string('status', 20)->default('running');
                $table->unsignedInteger('scanned_batches')->default(0);
                $table->unsignedInteger('qualifying_batches')->default(0);
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('database_sent')->default(0);
                $table->unsignedInteger('email_sent')->default(0);
                $table->unsignedInteger('whatsapp_sent')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->json('summary')->nullable();
                $table->timestamps();

                $table->index(['status', 'started_at'], 'inv_exp_scan_status_started_idx');
            });
        }

        if (! Schema::hasTable('inventory_expiry_alert_deliveries')) {
            Schema::create('inventory_expiry_alert_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('scan_run_id')
                    ->nullable()
                    ->constrained('inventory_expiry_scan_runs')
                    ->nullOnDelete();

                $table->foreignId('inventory_batch_id')
                    ->constrained('inventory_batches')
                    ->cascadeOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('location_id')
                    ->constrained('locations')
                    ->restrictOnDelete();

                $table->foreignId('recipient_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->date('expiry_date');
                $table->smallInteger('threshold_days');
                $table->integer('days_left');
                $table->string('severity', 20);
                $table->string('channel', 20);
                $table->string('status', 20)->default('pending');
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'inventory_batch_id',
                        'recipient_user_id',
                        'expiry_date',
                        'threshold_days',
                        'channel',
                    ],
                    'inv_exp_delivery_unique'
                );

                $table->index(
                    ['location_id', 'expiry_date', 'status'],
                    'inv_exp_delivery_location_date_idx'
                );

                $table->index(
                    ['recipient_user_id', 'created_at'],
                    'inv_exp_delivery_recipient_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_expiry_alert_deliveries');
        Schema::dropIfExists('inventory_expiry_scan_runs');
    }
};
