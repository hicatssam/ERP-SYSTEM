<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    public function enabled(): bool
    {
        return (bool) config(
            'services.whatsapp.enabled',
            false
        );
    }

    public function configured(): bool
    {
        return filled(
            config('services.whatsapp.token')
        )
            && filled(
                config(
                    'services.whatsapp.phone_number_id'
                )
            )
            && filled(
                config(
                    'services.whatsapp.graph_version'
                )
            );
    }

    public function sendText(
        string $phone,
        string $message
    ): ?Response {
        if (! $this->enabled()) {
            return null;
        }

        if (! $this->configured()) {
            throw new RuntimeException(
                'إعدادات WhatsApp Business Cloud API غير مكتملة.'
            );
        }

        $token = config(
            'services.whatsapp.token'
        );

        $phoneNumberId = config(
            'services.whatsapp.phone_number_id'
        );

        $graphVersion = config(
            'services.whatsapp.graph_version'
        );

        $phone = $this->normalizePhone(
            $phone
        );

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $graphVersion,
            $phoneNumberId
        );

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->post(
                $url,
                [
                    'messaging_product' => 'whatsapp',

                    'recipient_type' => 'individual',

                    'to' => $phone,

                    'type' => 'text',

                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'فشل إرسال رسالة WhatsApp: '
                . $response->body()
            );
        }

        return $response;
    }

    private function normalizePhone(
        string $phone
    ): string {
        return preg_replace(
            '/\D+/',
            '',
            $phone
        ) ?: '';
    }
}