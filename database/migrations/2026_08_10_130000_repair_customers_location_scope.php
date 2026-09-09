<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repairs the customers schema after an interrupted migrate:refresh.
     *
     * The original location migration is already marked as run, but its down()
     * method may have removed the column/indexes before failing on duplicate
     * phone values. This migration only restores the intended final schema.
     */
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        if (! Schema::hasColumn('customers', 'location_id')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreignId('location_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('locations')
                    ->restrictOnDelete();
            });
        } elseif (! $this->hasLocationForeignKey()) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreign('location_id')
                    ->references('id')
                    ->on('locations')
                    ->restrictOnDelete();
            });
        }

        // The old index made phone globally unique. It must be removed before
        // a phone can be reused in a different branch.
        if (Schema::hasIndex('customers', ['phone'], 'unique')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropUnique(['phone']);
            });
        }

        if (! Schema::hasIndex('customers', ['location_id', 'phone'], 'unique')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unique(
                    ['location_id', 'phone'],
                    'customers_location_id_phone_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Deliberately a no-op. Restoring a global unique phone index is not
        // safe once more than one branch can legitimately share a phone number.
    }

    private function hasLocationForeignKey(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

        return DB::table('information_schema.key_column_usage')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', 'customers')
            ->where('column_name', 'location_id')
            ->whereNotNull('referenced_table_name')
            ->exists();
    }
};
