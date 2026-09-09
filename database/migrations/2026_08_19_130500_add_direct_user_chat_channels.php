<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_channels', function (Blueprint $table) {
            /*
             * قنوات الفروع تستخدم location_id.
             * المحادثة المباشرة بين شخصين لا تحتاج Location داخل القناة.
             */
            $table
                ->unsignedBigInteger('location_id')
                ->nullable()
                ->change();
        });

        if (! Schema::hasColumn('chat_channels', 'direct_key')) {
            Schema::table('chat_channels', function (Blueprint $table) {
                $table
                    ->string('direct_key', 120)
                    ->nullable()
                    ->after('type');

                $table->unique(
                    'direct_key',
                    'chat_channels_direct_key_unique'
                );
            });
        }

        if (! Schema::hasTable('chat_channel_members')) {
            Schema::create('chat_channel_members', function (Blueprint $table) {
                $table->id();

                $table
                    ->foreignId('channel_id')
                    ->constrained('chat_channels')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table
                    ->timestamp('joined_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    ['channel_id', 'user_id'],
                    'chat_channel_members_channel_user_unique'
                );

                $table->index(
                    ['user_id', 'channel_id'],
                    'chat_channel_members_user_channel_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'chat_channel_members'
        );

        /*
         * نحذف القنوات المباشرة قبل إعادة location_id إلى NOT NULL.
         */
        DB::table('chat_channels')
            ->where('type', 'direct')
            ->delete();

        if (Schema::hasColumn('chat_channels', 'direct_key')) {
            Schema::table('chat_channels', function (Blueprint $table) {
                $table->dropUnique(
                    'chat_channels_direct_key_unique'
                );

                $table->dropColumn(
                    'direct_key'
                );
            });
        }

        Schema::table('chat_channels', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('location_id')
                ->nullable(false)
                ->change();
        });
    }
};