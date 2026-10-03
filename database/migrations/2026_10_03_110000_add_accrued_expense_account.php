<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounting_accounts')->updateOrInsert(['code' => '2200'], [
            'name' => 'مصروفات مستحقة', 'type' => 'liability', 'subtype' => null,
            'is_active' => true, 'is_system' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('accounting_accounts')->where('code', '2200')->delete();
    }
};
