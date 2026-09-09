<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('location_payment_account_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('location_payment_accounts')
                ->nullOnDelete();

            $table->index(['order_type', 'order_id', 'location_payment_account_id'], 'payments_order_account_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_order_account_idx');
            $table->dropConstrainedForeignId('location_payment_account_id');
        });
    }
};
