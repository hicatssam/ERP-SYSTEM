<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {

            if (!Schema::hasColumn('payment_methods', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('logo');
            }

            if (!Schema::hasColumn('payment_methods', 'description')) {
                $table->text('description')->nullable();
            }

            if (!Schema::hasColumn('payment_methods', 'api_key')) {
                $table->string('api_key')->nullable();
            }

            if (!Schema::hasColumn('payment_methods', 'notes')) {
                $table->text('notes')->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {

            $columns = [];

            if (Schema::hasColumn('payment_methods', 'logo_path')) {
                $columns[] = 'logo_path';
            }

            if (Schema::hasColumn('payment_methods', 'description')) {
                $columns[] = 'description';
            }

            if (Schema::hasColumn('payment_methods', 'api_key')) {
                $columns[] = 'api_key';
            }

            if (Schema::hasColumn('payment_methods', 'notes')) {
                $columns[] = 'notes';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }

        });
    }
};