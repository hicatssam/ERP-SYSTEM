<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'discount_type')) {
                $table->string('discount_type', 30)->default('none');
            }

            if (!Schema::hasColumn('orders', 'discount_value')) {
                $table->decimal('discount_value', 12, 3)->default(0);
            }

            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_discount_type')) {
                $table->string('channel_discount_type', 30)->nullable();
            }

            if (!Schema::hasColumn('orders', 'channel_discount_value')) {
                $table->decimal('channel_discount_value', 12, 3)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_discount_amount')) {
                $table->decimal('channel_discount_amount', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_discount_funding_rate')) {
                $table->decimal('channel_discount_funding_rate', 8, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_discount_funded_by_channel')) {
                $table->decimal('channel_discount_funded_by_channel', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_discount_funded_by_restaurant')) {
                $table->decimal('channel_discount_funded_by_restaurant', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'total_after_channel_discount')) {
                $table->decimal('total_after_channel_discount', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_commission_type')) {
                $table->string('channel_commission_type', 30)->nullable();
            }

            if (!Schema::hasColumn('orders', 'channel_commission_value')) {
                $table->decimal('channel_commission_value', 12, 3)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_commission_base')) {
                $table->string('channel_commission_base', 30)->nullable();
            }

            if (!Schema::hasColumn('orders', 'channel_commission_amount')) {
                $table->decimal('channel_commission_amount', 12, 2)->default(0);
            }

            if (!Schema::hasColumn('orders', 'channel_net_revenue')) {
                $table->decimal('channel_net_revenue', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'discount_type',
            'discount_value',
            'discount_amount',
            'channel_discount_type',
            'channel_discount_value',
            'channel_discount_amount',
            'channel_discount_funding_rate',
            'channel_discount_funded_by_channel',
            'channel_discount_funded_by_restaurant',
            'total_after_channel_discount',
            'channel_commission_type',
            'channel_commission_value',
            'channel_commission_base',
            'channel_commission_amount',
            'channel_net_revenue',
        ];

        $existingColumns = array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('orders', $column)
        );

        if ($existingColumns) {
            Schema::table('orders', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }
};