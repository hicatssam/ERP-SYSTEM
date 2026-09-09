<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('system_settings')
            ->where('key', 'chat_theme_preset')
            ->exists();

        if (! $exists) {
            DB::table('system_settings')->insert([
                'key' => 'chat_theme_preset',
                'value' => 'whatsapp-soft',
                'type' => 'string',
                'group' => 'chat',
                'label' => 'القالب الجاهز للمحادثة',
                'description' => 'قالب سريع لشكل المحادثة مثل واتساب أو الداكن أو الوردي.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'chat_theme_preset')
            ->delete();
    }
};