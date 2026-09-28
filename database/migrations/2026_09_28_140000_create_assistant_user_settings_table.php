<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('topic_mode', 20)->default('inherit');
            $table->json('allowed_intents')->nullable();
            $table->boolean('allow_action_suggestions')->default(false);
            $table->unsignedTinyInteger('max_items')->default(8);
            $table->timestamps();
        });

        if (Schema::hasTable('system_settings')) {
            $settings = [
                [
                    'key' => 'assistant_enabled',
                    'value' => '1',
                    'type' => 'boolean',
                    'group' => 'assistant',
                    'label' => 'تفعيل المساعد الذكي',
                    'description' => 'السماح للمستخدمين المؤهلين باستخدام مساعد النظام.',
                ],
                [
                    'key' => 'assistant_show_suggestions',
                    'value' => '1',
                    'type' => 'boolean',
                    'group' => 'assistant',
                    'label' => 'إظهار الأسئلة المقترحة',
                    'description' => 'إظهار بطاقات الأسئلة المقترحة حسب صلاحيات كل مستخدم.',
                ],
                [
                    'key' => 'assistant_read_only',
                    'value' => '1',
                    'type' => 'boolean',
                    'group' => 'assistant',
                    'label' => 'وضع القراءة فقط',
                    'description' => 'المساعد يقرأ ويحلل فقط ولا ينفذ أي تعديل أو عملية حساسة.',
                ],
                [
                    'key' => 'assistant_max_items',
                    'value' => '8',
                    'type' => 'integer',
                    'group' => 'assistant',
                    'label' => 'عدد النتائج المعروضة',
                    'description' => 'الحد الأقصى للعناصر التي تظهر في بطاقات الرد لكل مستخدم.',
                ],
            ];

            foreach ($settings as $setting) {
                SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_user_settings');

        if (Schema::hasTable('system_settings')) {
            SystemSetting::query()
                ->whereIn('key', [
                    'assistant_enabled',
                    'assistant_show_suggestions',
                    'assistant_read_only',
                    'assistant_max_items',
                ])
                ->delete();
        }
    }
};
