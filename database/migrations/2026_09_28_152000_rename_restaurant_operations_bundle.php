<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_bundles')) {
            return;
        }

        DB::table('module_bundles')
            ->where('code', 'restaurant_operations')
            ->where('name', 'تشغيل المطعم')
            ->update([
                'name' => 'تشغيل الصالة والمطبخ',
                'description' => 'نقطة البيع والصالة والطاولات والمطبخ وKDS للمطعم أو المخبز الذي يقدم جلوسًا.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('module_bundles')) {
            return;
        }

        DB::table('module_bundles')
            ->where('code', 'restaurant_operations')
            ->where('name', 'تشغيل الصالة والمطبخ')
            ->update([
                'name' => 'تشغيل المطعم',
                'description' => 'المطعم وPOS والطاولات والمطبخ وKDS.',
                'updated_at' => now(),
            ]);
    }
};
