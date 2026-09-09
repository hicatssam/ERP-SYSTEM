<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class ClientOnboardingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'business_legal_name' => ['', 'string', 'business', 'الاسم القانوني للمنشأة'],
            'business_registration_number' => ['', 'string', 'business', 'رقم التسجيل'],
            'business_tax_number' => ['', 'string', 'business', 'الرقم الضريبي'],
            'business_phone' => ['', 'string', 'business', 'هاتف المنشأة'],
            'business_email' => ['', 'string', 'business', 'بريد المنشأة'],
            'business_website' => ['', 'string', 'business', 'موقع المنشأة'],
            'business_country' => ['', 'string', 'business', 'الدولة'],
            'business_city' => ['', 'string', 'business', 'المدينة'],
            'business_address' => ['', 'string', 'business', 'العنوان'],

            'client_onboarding_completed' => ['0', 'boolean', 'onboarding', 'اكتمل إعداد العميل'],
            'client_onboarding_completed_at' => ['', 'string', 'onboarding', 'وقت اكتمال الإعداد'],
            'client_onboarding_version' => ['', 'string', 'onboarding', 'إصدار الإعداد'],
            'client_onboarding_run_id' => ['', 'integer', 'onboarding', 'رقم جلسة الإعداد'],
        ];

        foreach ($settings as $key => [$default, $type, $group, $label]) {
            $setting = SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => $default,
                    'type' => $type,
                    'group' => $group,
                    'label' => $label,
                    'description' => 'إعداد مخصص لمعالج إعداد العميل Sprint 9.',
                ]
            );

            $setting->forceFill([
                'type' => $type,
                'group' => $group,
                'label' => $label,
                'description' => 'إعداد مخصص لمعالج إعداد العميل Sprint 9.',
            ])->save();
        }

        SystemSetting::flushCache();
    }
}
