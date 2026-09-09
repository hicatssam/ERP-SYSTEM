<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kitchen_stations')) {
            Schema::create('kitchen_stations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('location_id');
                $table->string('name', 120);
                $table->string('code', 60);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('target_minutes')->default(15);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('location_id', 'kst_location_fk')
                    ->references('id')->on('locations')->cascadeOnDelete();

                $table->foreign('created_by', 'kst_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique(
                    ['location_id', 'code'],
                    'kst_location_code_uq'
                );

                $table->index(
                    ['location_id', 'is_active', 'sort_order'],
                    'kst_location_active_sort_idx'
                );
            });
        }

        if (! Schema::hasTable('kitchen_product_routes')) {
            Schema::create('kitchen_product_routes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('kitchen_station_id');
                $table->timestamps();

                $table->foreign('location_id', 'kpr_location_fk')
                    ->references('id')->on('locations')->cascadeOnDelete();

                $table->foreign('product_id', 'kpr_product_fk')
                    ->references('id')->on('products')->cascadeOnDelete();

                $table->foreign('kitchen_station_id', 'kpr_station_fk')
                    ->references('id')->on('kitchen_stations')->cascadeOnDelete();

                $table->unique(
                    ['location_id', 'product_id'],
                    'kpr_location_product_uq'
                );

                $table->index(
                    ['kitchen_station_id', 'product_id'],
                    'kpr_station_product_idx'
                );
            });
        }

        if (! Schema::hasTable('kitchen_category_routes')) {
            Schema::create('kitchen_category_routes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('kitchen_station_id');
                $table->timestamps();

                $table->foreign('location_id', 'kcr_location_fk')
                    ->references('id')->on('locations')->cascadeOnDelete();

                $table->foreign('category_id', 'kcr_category_fk')
                    ->references('id')->on('categories')->cascadeOnDelete();

                $table->foreign('kitchen_station_id', 'kcr_station_fk')
                    ->references('id')->on('kitchen_stations')->cascadeOnDelete();

                $table->unique(
                    ['location_id', 'category_id'],
                    'kcr_location_category_uq'
                );

                $table->index(
                    ['kitchen_station_id', 'category_id'],
                    'kcr_station_category_idx'
                );
            });
        }

        if (! Schema::hasTable('kitchen_tickets')) {
            Schema::create('kitchen_tickets', function (Blueprint $table): void {
                $table->id();
                $table->string('ticket_number', 64)->unique();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('kitchen_station_id');
                $table->string('status', 24)->default('queued');
                $table->unsignedTinyInteger('priority')->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('dispatched_by')->nullable();
                $table->unsignedBigInteger('started_by')->nullable();
                $table->unsignedBigInteger('ready_by')->nullable();
                $table->unsignedBigInteger('served_by')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamps();

                $table->foreign('order_id', 'kt_order_fk')
                    ->references('id')->on('orders')->restrictOnDelete();

                $table->foreign('location_id', 'kt_location_fk')
                    ->references('id')->on('locations')->restrictOnDelete();

                $table->foreign('kitchen_station_id', 'kt_station_fk')
                    ->references('id')->on('kitchen_stations')->restrictOnDelete();

                $table->foreign('dispatched_by', 'kt_dispatched_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->foreign('started_by', 'kt_started_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->foreign('ready_by', 'kt_ready_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->foreign('served_by', 'kt_served_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->foreign('cancelled_by', 'kt_cancelled_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique(
                    ['order_id', 'kitchen_station_id'],
                    'kt_order_station_uq'
                );

                $table->index(
                    ['location_id', 'status', 'priority', 'queued_at'],
                    'kt_kds_scope_idx'
                );
            });
        }

        if (! Schema::hasTable('kitchen_ticket_items')) {
            Schema::create('kitchen_ticket_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('kitchen_ticket_id');
                $table->unsignedBigInteger('order_item_id');
                $table->unsignedBigInteger('product_id');
                $table->string('product_name', 255);
                $table->decimal('quantity', 12, 3);
                $table->string('status', 24)->default('queued');
                $table->string('routing_source', 30)->nullable();
                $table->text('kitchen_notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->foreign('kitchen_ticket_id', 'kti_ticket_fk')
                    ->references('id')->on('kitchen_tickets')->cascadeOnDelete();

                $table->foreign('order_item_id', 'kti_order_item_fk')
                    ->references('id')->on('order_items')->restrictOnDelete();

                $table->foreign('product_id', 'kti_product_fk')
                    ->references('id')->on('products')->restrictOnDelete();

                // Every order item belongs to exactly one kitchen ticket.
                $table->unique(
                    'order_item_id',
                    'kti_order_item_uq'
                );

                $table->index(
                    ['kitchen_ticket_id', 'status'],
                    'kti_ticket_status_idx'
                );
            });
        }

        if (
            Schema::hasTable('orders')
            && ! Schema::hasColumn('orders', 'kitchen_dispatched_at')
        ) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->timestamp('kitchen_dispatched_at')
                    ->nullable()
                    ->after('guest_count');
            });
        }

        if (
            Schema::hasTable('order_items')
            && ! Schema::hasColumn('order_items', 'kitchen_notes')
        ) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->text('kitchen_notes')
                    ->nullable()
                    ->after('line_total');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('order_items')
            && Schema::hasColumn('order_items', 'kitchen_notes')
        ) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn('kitchen_notes');
            });
        }

        if (
            Schema::hasTable('orders')
            && Schema::hasColumn('orders', 'kitchen_dispatched_at')
        ) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('kitchen_dispatched_at');
            });
        }

        Schema::dropIfExists('kitchen_ticket_items');
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('kitchen_category_routes');
        Schema::dropIfExists('kitchen_product_routes');
        Schema::dropIfExists('kitchen_stations');
    }
};
