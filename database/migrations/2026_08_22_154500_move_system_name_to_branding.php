<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $arabic = DB::table('system_settings')
            ->where('key', 'system_name')
            ->first();

        $english = trim(
            (string) DB::table('system_settings')
                ->where('key', 'system_name_en')
                ->value('value')
        );

        /*
         * system_name كان إعداداً قديماً ضمن general،
         * بينما بقية بيانات الهوية موجودة ضمن branding.
         *
         * ننقل metadata فقط ونحافظ على القيمة الحالية.
         */
        if ($arabic) {
            $value = trim(
                (string) $arabic->value
            );

            /*
             * تنظيف آمن لقيمة Dahab القديمة فقط عندما يتضح أن
             * المستخدم أعاد تسمية الهوية الإنجليزية إلى علامة أخرى.
             *
             * بهذه الحالة نترك العربي فارغاً ليعمل fallback إلى
             * system_name_en بدلاً من إظهار "حلويات دهب".
             *
             * أي قيمة عربية مخصصة أخرى لا يتم لمسها.
             */
            $legacyArabic = [
                'حلويات دهب',
                'دهب',
            ];

            $legacyEnglish = [
                '',
                'Dahab Sweets',
                'DAHAB SWEETS',
            ];

            if (
                in_array($value, $legacyArabic, true)
                && ! in_array($english, $legacyEnglish, true)
            ) {
                $value = '';
            }

            DB::table('system_settings')
                ->where('key', 'system_name')
                ->update([
                    'value' => $value,
                    'type' => 'string',
                    'group' => 'branding',
                    'label' => 'اسم العلامة بالعربية',
                    'description' =>
                        'الاسم العربي الرئيسي للهوية. إذا ترك فارغاً تستخدم الشاشات الاسم الإنجليزي.',
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('system_settings')
                ->insert([
                    'key' => 'system_name',
                    'value' => '',
                    'type' => 'string',
                    'group' => 'branding',
                    'label' => 'اسم العلامة بالعربية',
                    'description' =>
                        'الاسم العربي الرئيسي للهوية. إذا ترك فارغاً تستخدم الشاشات الاسم الإنجليزي.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        SystemSetting::flushCache();
    }

    public function down(): void
    {
        /*
         * لا نعيد قيمة قديمة مثل "حلويات دهب".
         * فقط نعيد تصنيف setting إلى general للمحافظة على البيانات.
         */
        DB::table('system_settings')
            ->where('key', 'system_name')
            ->update([
                'group' => 'general',
                'label' => 'اسم النظام',
                'description' => 'اسم النظام الأساسي.',
                'updated_at' => now(),
            ]);

        SystemSetting::flushCache();
    }
};
