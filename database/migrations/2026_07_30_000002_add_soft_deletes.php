<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft-delete support to core transactional tables.
     * Hard deletes are replaced by setting deleted_at; existing data is untouched.
     */
    public function up(): void
    {
        $tables = [
            'customers',
            'products',
            'employees',
            'locations',
            'orders',
            'special_cake_orders',
            'invoices',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'customers',
            'products',
            'employees',
            'locations',
            'orders',
            'special_cake_orders',
            'invoices',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
