<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't enforce ENUM; just rebuild the table definition
            // by modifying the column type – Doctrine handles it via recreate.
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->string('reason', 100)->change();
            });
        } else {
            // MySQL / PostgreSQL: alter the column to a plain VARCHAR
            DB::statement('ALTER TABLE stock_movements MODIFY COLUMN reason VARCHAR(100) NOT NULL');
        }
    }

    public function down(): void
    {
        $allowed = implode("','", [
            'factory_production', 'stock_received', 'order', 'order_sale',
            'order_cancellation', 'damaged', 'expired', 'return',
            'internal_use', 'correction', 'opening_stock',
            'stock_count_adjustment', 'manual_adjustment',
            'transfer_dispatch', 'transfer_receipt',
        ]);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN reason ENUM('{$allowed}') NOT NULL");
        }
        // SQLite: no-op — the column stays as string on rollback
    }
};
