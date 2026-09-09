<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('logo');
            $table->text('description')->nullable()->after('type');
            $table->string('api_key')->nullable()->after('description');
            $table->text('notes')->nullable()->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'description', 'api_key', 'notes']);
        });
    }
};