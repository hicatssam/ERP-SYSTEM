<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_message_receipts')) {
            return;
        }

        Schema::create('chat_message_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('chat_messages')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['message_id', 'user_id'],
                'chat_message_receipts_message_user_unique'
            );

            $table->index(
                ['user_id', 'delivered_at'],
                'chat_message_receipts_user_delivered_idx'
            );

            $table->index(
                ['user_id', 'read_at'],
                'chat_message_receipts_user_read_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_receipts');
    }
};