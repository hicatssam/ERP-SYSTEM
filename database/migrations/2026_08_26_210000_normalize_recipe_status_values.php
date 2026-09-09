<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recipes') || ! Schema::hasColumn('recipes', 'status')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('recipes')
                ->whereIn('status', ['active', 'published', 'enabled'])
                ->update([
                    'status' => 'approved',
                    'is_active' => true,
                ]);

            DB::table('recipes')
                ->whereIn('status', ['inactive', 'disabled'])
                ->update([
                    'status' => 'archived',
                    'is_active' => false,
                ]);

            DB::table('recipes')
                ->whereIn('status', ['pending', 'new'])
                ->update([
                    'status' => 'draft',
                    'is_active' => false,
                ]);

            DB::table('recipes')
                ->whereNotIn('status', ['draft', 'approved', 'archived'])
                ->update([
                    'status' => 'draft',
                    'is_active' => false,
                ]);
        });
    }

    public function down(): void
    {
        // Canonical workflow values must not be changed back to legacy values.
    }
};
