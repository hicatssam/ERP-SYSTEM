<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        if (Schema::hasTable('leave_types')) {
            $now = now();

            foreach ([
                [
                    'code' => 'annual',
                    'name' => 'إجازة سنوية',
                    'is_paid' => true,
                    'annual_days' => 14,
                ],
                [
                    'code' => 'sick',
                    'name' => 'إجازة مرضية',
                    'is_paid' => true,
                    'annual_days' => null,
                ],
                [
                    'code' => 'unpaid',
                    'name' => 'إجازة بدون راتب',
                    'is_paid' => false,
                    'annual_days' => null,
                ],
            ] as $type) {
                DB::table('leave_types')->updateOrInsert(
                    ['code' => $type['code']],
                    [
                        ...$type,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        $settings = [
            'attendance_enabled' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'تفعيل الحضور والدوام',
                'description' => 'التفعيل العام لنظام الحضور والورديات والإجازات.',
            ],
            'attendance_biometric_enabled' => [
                'value' => 0,
                'type' => 'boolean',
                'label' => 'تفعيل أجهزة البصمة',
                'description' => 'تفعيل إدارة الأجهزة وواجهات Push/Connector.',
            ],
            'attendance_device_require_approval' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'اعتماد سجلات الجهاز',
                'description' => 'يتطلب اعتماد سجلات البصمة قبل دخولها في الرواتب.',
            ],
            'payroll_late_deduction_enabled' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'خصم التأخير والخروج المبكر',
                'description' => 'إنشاء خصم تلقائي من الحضور المعتمد.',
            ],
            'payroll_absence_deduction_enabled' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'خصم الغياب',
                'description' => 'إنشاء خصم يومي للغياب المسجل والمعتمد.',
            ],
            'payroll_overtime_enabled' => [
                'value' => 1,
                'type' => 'boolean',
                'label' => 'احتساب الساعات الإضافية',
                'description' => 'إنشاء بدل إضافي من ساعات العمل الإضافية المعتمدة.',
            ],
            'payroll_standard_work_days_per_month' => [
                'value' => 26,
                'type' => 'decimal',
                'label' => 'أيام العمل الشهرية',
                'description' => 'عدد أيام العمل القياسية المستخدمة لحساب سعر اليوم.',
            ],
            'payroll_standard_hours_per_day' => [
                'value' => 8,
                'type' => 'decimal',
                'label' => 'ساعات العمل اليومية',
                'description' => 'عدد ساعات العمل القياسية المستخدمة لحساب سعر الساعة.',
            ],
            'payroll_overtime_multiplier' => [
                'value' => 1.5,
                'type' => 'decimal',
                'label' => 'معامل الساعات الإضافية',
                'description' => 'المعامل المالي المستخدم عند حساب بدل الساعات الإضافية.',
            ],
        ];

        foreach ($settings as $key => $meta) {
            $setting = SystemSetting::query()
                ->firstOrNew(['key' => $key]);

            if (! $setting->exists) {
                $setting->value = (string) $meta['value'];
            }

            $setting->type = $setting->type ?: $meta['type'];
            $setting->group = $setting->group ?: 'attendance_payroll';
            $setting->label = $setting->label ?: $meta['label'];
            $setting->description =
                $setting->description ?: $meta['description'];

            $setting->save();
        }

        SystemSetting::flushCache();
    }
}
