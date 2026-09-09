<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $missing = [
            'order_source' => ! Schema::hasColumn('orders', 'order_source'),
            'public_token' => ! Schema::hasColumn('orders', 'public_token'),
            'public_request_id' => ! Schema::hasColumn('orders', 'public_request_id'),
            'public_order_meta' => ! Schema::hasColumn('orders', 'public_order_meta'),
            'customer_status_message' => ! Schema::hasColumn('orders', 'customer_status_message'),
            'customer_status_message_updated_at' => ! Schema::hasColumn('orders', 'customer_status_message_updated_at'),
            'customer_status_message_by' => ! Schema::hasColumn('orders', 'customer_status_message_by'),
        ];

        if (! in_array(true, $missing, true)) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($missing): void {
            if ($missing['order_source']) {
                $table->string('order_source', 40)
                    ->nullable()
                    ->after('created_by')
                    ->index();
            }

            if ($missing['public_token']) {
                $table->string('public_token', 64)
                    ->nullable()
                    ->unique()
                    ->after('order_source');
            }

            if ($missing['public_request_id']) {
                $table->string('public_request_id', 64)
                    ->nullable()
                    ->unique()
                    ->after('public_token');
            }

            if ($missing['public_order_meta']) {
                $table->json('public_order_meta')
                    ->nullable()
                    ->after('public_request_id');
            }

            if ($missing['customer_status_message']) {
                $table->text('customer_status_message')
                    ->nullable()
                    ->after('public_order_meta');
            }

            if ($missing['customer_status_message_updated_at']) {
                $table->timestamp('customer_status_message_updated_at')
                    ->nullable()
                    ->after('customer_status_message');
            }

            if ($missing['customer_status_message_by']) {
                $table->unsignedBigInteger('customer_status_message_by')
                    ->nullable()
                    ->after('customer_status_message_updated_at')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $columns = [
            'customer_status_message_by',
            'customer_status_message_updated_at',
            'customer_status_message',
            'public_order_meta',
            'public_request_id',
            'public_token',
            'order_source',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn('orders', $column)
        ));

        if ($existing !== []) {
            Schema::table('orders', function (Blueprint $table) use ($existing): void {
                $table->dropColumn($existing);
            });
        }
    }
};
