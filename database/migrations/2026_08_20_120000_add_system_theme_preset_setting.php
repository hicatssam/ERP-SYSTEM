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

        $exists =
            DB::table('system_settings')
                ->where(
                    'key',
                    'system_theme_preset'
                )
                ->exists();

        if ($exists) {
            return;
        }

        DB::table('system_settings')
            ->insert([
                'key' =>
                    'system_theme_preset',

                /*
                 * "custom" intentionally preserves the currently saved theme.
                 * Choosing a preset later writes the preset colors explicitly.
                 */
                'value' =>
                    'custom',

                'type' =>
                    'select',

                'group' =>
                    'theme',

                'label' =>
                    'قالب ثيم النظام',

                'description' =>
                    'القالب الجاهز المختار للثيم العام، أو مخصص عند تعديل الألوان يدويًا.',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where(
                'key',
                'system_theme_preset'
            )
            ->delete();
    }
};