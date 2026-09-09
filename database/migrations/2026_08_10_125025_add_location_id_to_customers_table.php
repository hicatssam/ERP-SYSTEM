<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('id')
                ->constrained('locations')
                ->restrictOnDelete();

            // السماح بتكرار رقم العميل في فروع مختلفة
            $table->dropUnique(['phone']);
            $table->unique(['location_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['location_id', 'phone']);
            $table->dropConstrainedForeignId('location_id');
            $table->unique('phone');
        });
    }
};