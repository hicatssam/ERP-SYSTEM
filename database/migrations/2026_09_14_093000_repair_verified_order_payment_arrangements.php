<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (
            ! Schema::hasColumn('orders', 'payment_arrangement')
            || ! Schema::hasColumn('orders', 'payment_status')
        ) {
            return;
        }

        // Older verified transfer orders kept the transitional arrangement
        // `pending_verification` even after payment_status became `paid`.
        // That made the admin order page incorrectly look like the payment was
        // still waiting for review. Repair only orders that are already fully paid.
        DB::table('orders')
            ->where('payment_arrangement', 'pending_verification')
            ->where('payment_status', 'paid')
            ->update([
                'payment_arrangement' => 'pay_now',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data repair is intentionally irreversible. We cannot safely infer
        // which pay-now orders originally came from a verification workflow.
    }
};
