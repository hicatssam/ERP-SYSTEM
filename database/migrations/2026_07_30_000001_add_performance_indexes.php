<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add only the indexes that are NOT already defined in the original migrations.
 *
 * Already covered by original migrations:
 * - orders: location_id, created_by, status
 * - stock_movements: [location_id, product_id] composite, created_by
 * - invoices: location_id, status, issued_at
 * - cash_sessions: [location_id, status] composite, employee_id
 * - stock_counts: location_id, status
 * - special_cake_orders: origin_branch_id, status, required_date
 * - sales_ledger_entries: [location_id, entry_date] composite, entry_type
 */
return new class extends Migration
{
    public function up(): void
    {
        // orders: customer_id used in customer history queries
        Schema::table('orders', function (Blueprint $table) {
            $table->index('customer_id');
        });

        // payments: location_id used in location financial summaries
        Schema::table('payments', function (Blueprint $table) {
            $table->index('location_id');
        });

        // invoices: customer_id and issued_by for lookups and reporting
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('customer_id');
            $table->index('issued_by');
        });

        // special_cake_orders: assigned_to and factory_location_id for workflow queries
        Schema::table('special_cake_orders', function (Blueprint $table) {
            $table->index('assigned_to');
            $table->index('factory_location_id');
        });

        // stock_counts: created_by for auditing
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->index('created_by');
        });

        // sales_ledger_entries: created_by for auditing
        Schema::table('sales_ledger_entries', function (Blueprint $table) {
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['location_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['issued_by']);
        });

        Schema::table('special_cake_orders', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['factory_location_id']);
        });

        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('sales_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });
    }
};
