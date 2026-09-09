<?php

use App\Models\SystemSetting;
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

        $settings = [
            ['customer_menu_enabled', '1', 'boolean', 'تفعيل منيو الطلب للعملاء', 'إتاحة رابط المنيو العام والطلب المباشر للعملاء.'],
            ['customer_menu_title', 'اطلب مباشرة', 'string', 'عنوان منيو العميل', 'عنوان قصير يظهر أعلى صفحة المنيو.'],
            ['customer_menu_subtitle', 'اختر طلبك وسنجهزه لك', 'string', 'وصف منيو العميل', 'وصف قصير تحت اسم المطعم.'],
            ['customer_menu_primary_color', '#6D5A8D', 'color', 'اللون الرئيسي لمنيو العميل', 'لون الأزرار والعناصر الرئيسية.'],
            ['customer_menu_accent_color', '#F08A78', 'color', 'اللون المساند', 'لون الشارات واللمسات الثانوية.'],
            ['customer_menu_background_color', '#F7F5FA', 'color', 'لون الخلفية', 'خلفية صفحة منيو العميل.'],
            ['customer_menu_surface_color', '#FFFFFF', 'color', 'لون البطاقات', 'لون بطاقات الأصناف والسلة.'],
            ['customer_menu_text_color', '#251F2B', 'color', 'لون النص', 'لون النصوص الأساسية.'],
            ['customer_menu_muted_color', '#77717F', 'color', 'لون النص الثانوي', 'لون الأوصاف والنصوص الثانوية.'],
            ['customer_menu_border_color', '#E7E1EC', 'color', 'لون الحدود', 'حدود البطاقات والحقول.'],
            ['customer_menu_radius', '18', 'integer', 'استدارة البطاقات', 'نصف قطر الحواف من 0 إلى 32 بكسل.'],
            ['customer_menu_show_search', '1', 'boolean', 'إظهار البحث', 'إظهار حقل البحث داخل منيو العميل.'],
            ['customer_menu_show_categories', '1', 'boolean', 'إظهار الفئات', 'إظهار شريط الفئات أعلى المنتجات.'],
            ['customer_menu_show_unavailable', '0', 'boolean', 'إظهار غير المتاح', 'عند التفعيل يظهر الصنف غير المتاح مع تعطيل زر الإضافة.'],
            ['customer_menu_allow_dine_in', '1', 'boolean', 'السماح بطلب داخل المطعم', 'يظهر خيار داخل المطعم في إنهاء الطلب.'],
            ['customer_menu_allow_takeaway', '1', 'boolean', 'السماح بالسفري', 'يظهر خيار سفري في إنهاء الطلب.'],
            ['customer_menu_allow_delivery', '0', 'boolean', 'السماح بالتوصيل', 'يظهر خيار توصيل ويطلب عنوان العميل.'],
            ['customer_menu_checkout_button_text', 'إرسال الطلب', 'string', 'نص زر إنهاء الطلب', 'النص الظاهر على الزر الرئيسي في السلة.'],
            ['customer_menu_sales_channel_id', '', 'integer', 'قناة البيع للطلبات العامة', 'إن تركت فارغة سيستخدم النظام قناة Website الفعالة تلقائياً.'],
            ['customer_menu_cover_image', '', 'image', 'صورة غلاف منيو العميل', 'صورة اختيارية للواجهة. اسم المطعم والشعار يؤخذان من Branding نفسه.'],
        ];

        foreach ($settings as [$key, $default, $type, $label, $description]) {
            $existing = DB::table('system_settings')
                ->where('key', $key)
                ->first();

            if ($existing) {
                DB::table('system_settings')
                    ->where('key', $key)
                    ->update([
                        'type' => $type,
                        'group' => 'branding',
                        'label' => $label,
                        'description' => $description,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('system_settings')->insert([
                'key' => $key,
                'value' => $default,
                'type' => $type,
                'group' => 'branding',
                'label' => $label,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        SystemSetting::flushCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('key', 'like', 'customer_menu_%')
            ->delete();

        SystemSetting::flushCache();
    }
};
