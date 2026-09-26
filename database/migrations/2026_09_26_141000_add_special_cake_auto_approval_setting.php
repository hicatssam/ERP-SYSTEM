<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $exists = DB::table('system_settings')
            ->where('key', 'special_cake_auto_approval')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('system_settings')->insert([
            'key' => 'special_cake_auto_approval',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'branding',
            'label' => 'الموافقة التلقائية على طلب الكيك الخاص',
            'description' => 'عند التفعيل ينتقل طلب الكيك الخاص الجديد مباشرة إلى قيد التنفيذ بموافقة تلقائية من النظام. عند الإلغاء يبدأ الطلب قيد المراجعة ويحتاج موافقة يدوية.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('key', 'special_cake_auto_approval')
            ->delete();
    }
};
