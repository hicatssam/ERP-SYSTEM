<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('sender_name', 150)->nullable()->after('payment_proof');
            $table->string('sender_phone', 50)->nullable()->after('sender_name');
            $table->string('sender_account_number', 120)->nullable()->after('sender_phone');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'sender_name',
                'sender_phone',
                'sender_account_number',
            ]);
        });
    }
};
