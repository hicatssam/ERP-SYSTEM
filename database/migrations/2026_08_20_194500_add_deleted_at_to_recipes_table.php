<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('recipes')
            || Schema::hasColumn('recipes', 'deleted_at')
        ) {
            return;
        }

        Schema::table('recipes', function (Blueprint $table): void {
            /*
             * Recipe model uses SoftDeletes.
             * Keep historical/versioned recipes instead of physically deleting them.
             */
            $table
                ->softDeletes()
                ->after('updated_at');
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('recipes')
            || ! Schema::hasColumn('recipes', 'deleted_at')
        ) {
            return;
        }

        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};