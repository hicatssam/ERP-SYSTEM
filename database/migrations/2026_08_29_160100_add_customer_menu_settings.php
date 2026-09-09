<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            ['customer_menu_primary','#8F112F','color','اللون الرئيسي','الهيدر والأزرار والعناصر البارزة.'],
            ['customer_menu_accent','#E9A91B','color','لون التمييز','زر الإضافة وحالات النشاط.'],
            ['customer_menu_background','#F7F4EF','color','خلفية المنيو','لون خلفية الصفحة.'],
            ['customer_menu_surface','#FFFFFF','color','لون البطاقات','خلفية المنتجات والسلة.'],
            ['customer_menu_text','#191919','color','لون النص','العناوين والأسعار.'],
            ['customer_menu_muted','#707070','color','لون النص الثانوي','الوصف والملاحظات.'],
            ['customer_menu_radius','18','integer','استدارة الزوايا','0 إلى 40 بكسل.'],
            ['customer_menu_columns','3','integer','عدد الأعمدة','عدد بطاقات المنتج في الصف على الشاشة الكبيرة.'],
            ['customer_menu_hero_height','360','integer','ارتفاع الغلاف','220 إلى 720 بكسل.'],
            ['customer_menu_show_hero','1','boolean','إظهار غلاف المنيو','تشغيل أو إخفاء قسم الغلاف.'],
            ['customer_menu_cover','','image','صورة غلاف المنيو','صورة عريضة WEBP أو JPG.'],
        ];

        foreach ($settings as [$key,$value,$type,$label,$description]) {
            DB::table('system_settings')->updateOrInsert(['key' => $key], compact('value','type','label','description') + ['group' => 'customer_menu','created_at' => $now,'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->where('group', 'customer_menu')->delete();
    }
};
