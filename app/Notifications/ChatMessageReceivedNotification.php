<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ChatMessage $chatMessage
    ) {
    }

    public function via(
        object $notifiable
    ): array {
        /*
         * Synchronous Database Notification.
         * لا يحتاج Queue Worker حتى يصل الإشعار.
         */
        return [
            'database',
        ];
    }

    public function toDatabase(
        object $notifiable
    ): array {
        $message =
            $this->chatMessage
                ->loadMissing([
                    'channel.location',
                    'channel.members.user.employee',
                    'user.employee',
                    'attachments',
                ]);

        $senderName = $message->user?->display_name ?? 'مستخدم نظام';

        $body =
            trim(
                (string)
                $message->message
            );

        $preview =
            $body !== ''
                ? mb_strimwidth(
                    $body,
                    0,
                    100,
                    '…'
                )
                : (
                    $message
                        ->attachments
                        ->isNotEmpty()
                            ? 'أرسل مرفقًا'
                            : 'أرسل رسالة جديدة'
                );

        $isDirect =
            $message
                ->channel
                ?->type
            === 'direct';

        $context =
            $isDirect
                ? 'رسالة خاصة'
                : (
                    $message
                        ->channel
                        ?->location
                        ?->name

                    ?? $message
                        ->channel
                        ?->name

                    ?? 'المحادثات'
                );

        return [
            'fingerprint' =>
                'chat_message_'
                . $message->id
                . '_'
                . $notifiable->id,

            'type' =>
                'chat_message',

            'chat_type' =>
                $isDirect
                    ? 'direct'
                    : 'branch',

            'title' =>
                'رسالة جديدة من '
                . $senderName,

            'message' =>
                $context
                . ' — '
                . $preview,

            'chat_message_id' =>
                $message->id,

            'channel_id' =>
                $message
                    ->channel_id,

            'location_id' =>
                $message
                    ->channel
                    ?->location_id,

            'sender_id' =>
                $message
                    ->user_id,

            'sender_name' =>
                $senderName,

            'url' =>
                '/chat/'
                . $message
                    ->channel_id,

            'priority' =>
                'medium',
        ];
    }
}
