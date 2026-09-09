<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->foreignId('purchase_unit_id')->nullable()->after('currency_id')
                ->constrained('units')->nullOnDelete();
            $table->decimal('conversion_factor', 18, 6)->default(1)->after('purchase_unit_id');
            $table->string('package_description')->nullable()->after('conversion_factor');
        });

        Schema::table('supplier_product_price_histories', function (Blueprint $table): void {
            $table->foreignId('purchase_unit_id')->nullable()->after('currency_id')
                ->constrained('units')->nullOnDelete();
            $table->string('purchase_unit_snapshot', 120)->nullable()->after('purchase_unit_id');
            $table->decimal('conversion_factor', 18, 6)->default(1)->after('purchase_unit_snapshot');
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->foreignId('supplier_product_id')->nullable()->after('product_id')
                ->constrained('supplier_products')->nullOnDelete();
            $table->foreignId('purchase_unit_id')->nullable()->after('supplier_product_id')
                ->constrained('units')->nullOnDelete();
            $table->string('supplier_sku_snapshot', 100)->nullable()->after('purchase_unit_id');
            $table->string('purchase_unit_snapshot', 120)->nullable()->after('supplier_sku_snapshot');
            $table->decimal('conversion_factor', 18, 6)->default(1)->after('purchase_unit_snapshot');
            $table->decimal('ordered_base_quantity', 18, 3)->default(0)->after('ordered_quantity');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->foreignId('purchase_unit_id')->nullable()->after('product_id')
                ->constrained('units')->nullOnDelete();
            $table->string('purchase_unit_snapshot', 120)->nullable()->after('purchase_unit_id');
            $table->decimal('conversion_factor', 18, 6)->default(1)->after('purchase_unit_snapshot');
            $table->decimal('received_base_quantity', 18, 3)->default(0)->after('received_quantity');
            $table->decimal('accepted_base_quantity', 18, 3)->default(0)->after('accepted_quantity');
            $table->decimal('rejected_base_quantity', 18, 3)->default(0)->after('rejected_quantity');
        });

        Schema::table('purchase_return_items', function (Blueprint $table): void {
            $table->decimal('conversion_factor', 18, 6)->default(1)->after('return_quantity');
            $table->decimal('return_base_quantity', 18, 3)->default(0)->after('conversion_factor');
        });

        // Existing records used the inventory unit directly, so factor 1
        // preserves their exact historical quantities and costs.
        DB::table('purchase_order_items')->update([
            'ordered_base_quantity' => DB::raw('ordered_quantity'),
        ]);
        DB::table('goods_receipt_items')->update([
            'received_base_quantity' => DB::raw('received_quantity'),
            'accepted_base_quantity' => DB::raw('accepted_quantity'),
            'rejected_base_quantity' => DB::raw('rejected_quantity'),
        ]);
        DB::table('purchase_return_items')->update([
            'return_base_quantity' => DB::raw('return_quantity'),
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table): void {
            $table->dropColumn(['conversion_factor', 'return_base_quantity']);
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn([
                'purchase_unit_snapshot', 'conversion_factor',
                'received_base_quantity', 'accepted_base_quantity', 'rejected_base_quantity',
            ]);
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_product_id');
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn([
                'supplier_sku_snapshot', 'purchase_unit_snapshot',
                'conversion_factor', 'ordered_base_quantity',
            ]);
        });

        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn(['conversion_factor', 'package_description']);
        });

        Schema::table('supplier_product_price_histories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn(['purchase_unit_snapshot', 'conversion_factor']);
        });
    }
};
