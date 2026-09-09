<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_channels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('location_id')
                ->constrained('locations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 160);
            $table->string('type', 30)->default('branch');
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['location_id', 'type']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_id')
                ->constrained('chat_channels')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('reply_to_id')
                ->nullable()
                ->constrained('chat_messages')
                ->nullOnDelete();

            $table->text('message')->nullable();
            $table->string('message_type', 20)->default('text');

            $table->timestamps();

            $table->index(['channel_id', 'id']);
            $table->index(['channel_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('chat_messages')
                ->cascadeOnDelete();

            $table->string('disk', 30)->default('public');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->timestamps();

            $table->index('message_id');
        });

        Schema::create('chat_reads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_id')
                ->constrained('chat_channels')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('last_read_message_id')
                ->nullable()
                ->constrained('chat_messages')
                ->nullOnDelete();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reads');
        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_channels');
    }
};
