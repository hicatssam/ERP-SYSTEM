<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'public_token')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->uuid('public_token')->nullable()->unique()->after('order_number');
            });
        }

        if (! Schema::hasColumn('orders', 'public_request_token')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->uuid('public_request_token')->nullable()->unique()->after('public_token');
            });
        }

        if (! Schema::hasColumn('orders', 'guest_name')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('guest_name', 120)->nullable()->after('customer_id');
            });
        }

        if (! Schema::hasColumn('orders', 'guest_phone')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('guest_phone', 30)->nullable()->after('guest_name');
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_address')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->text('delivery_address')->nullable()->after('guest_phone');
            });
        }

        if (! Schema::hasColumn('orders', 'order_source')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('order_source', 30)->default('dashboard')->after('delivery_address');
            });
        }
    }

    public function down(): void
    {
        // Compatibility migration: some installations already had part of
        // these columns. A no-op rollback avoids deleting pre-existing data.
    }
};
