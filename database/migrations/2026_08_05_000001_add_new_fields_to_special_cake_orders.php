<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_cake_orders', function (Blueprint $table) {
            // Image cover type (type of printed image on cake)
            $table->enum('image_cover_type', ['none', 'edible_sugar', 'removable_cardboard'])
                  ->default('none')
                  ->after('special_instructions');

            // Discount fields
            $table->enum('discount_type', ['none', 'percentage', 'fixed'])
                  ->default('none')
                  ->after('total_price');
            $table->decimal('discount_value', 8, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
            $table->decimal('net_price', 12, 2)->default(0)->after('discount_amount');

            // Payment channel (how the customer transfers money)
            $table->string('payment_channel', 50)->nullable()->after('payment_arrangement');
        });

        // Back-fill net_price for existing records
        \DB::statement('UPDATE special_cake_orders SET net_price = total_price WHERE net_price = 0');
    }

    public function down(): void
    {
        Schema::table('special_cake_orders', function (Blueprint $table) {
            $table->dropColumn([
                'image_cover_type',
                'discount_type',
                'discount_value',
                'discount_amount',
                'net_price',
                'payment_channel',
            ]);
        });
    }
};
