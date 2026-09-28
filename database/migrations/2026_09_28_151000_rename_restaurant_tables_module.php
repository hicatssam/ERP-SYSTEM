<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        DB::table('modules')
            ->where('code', 'restaurant_tables')
            ->where('name', 'طاولات المطعم')
            ->update([
                'name' => 'الصالة والطاولات',
                'description' => 'مناطق الجلوس والطاولات والجلسات وربط الطلب بها للمطعم أو المخبز الذي يقدم جلوسًا.',
                'updated_at' => now(),
            ]);

        Cache::forget('modules:registry:v1');
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        DB::table('modules')
            ->where('code', 'restaurant_tables')
            ->where('name', 'الصالة والطاولات')
            ->update([
                'name' => 'طاولات المطعم',
                'description' => 'مناطق المطعم والطاولات والجلسات المفتوحة وربط الطلب بالطاولة.',
                'updated_at' => now(),
            ]);

        Cache::forget('modules:registry:v1');
    }
};
