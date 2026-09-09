<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsAppGateway
{
    public function configured(): bool
    {
        return (bool) config('inventory_expiry.whatsapp.enabled', false)
            && filled(config('inventory_expiry.whatsapp.phone_number_id'))
            && filled(config('inventory_expiry.whatsapp.token'));
    }

    public function sendText(string $phone, string $message): array
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'WhatsApp Business Cloud API غير مهيأ. أضف بيانات WHATSAPP_* إلى ملف .env.'
            );
        }

        $to = $this->normalizePhone($phone);

        if ($to === '') {
            throw new RuntimeException('رقم واتساب المستلم غير صالح.');
        }

        $baseUrl = rtrim(
            (string) config('inventory_expiry.whatsapp.base_url'),
            '/'
        );

        $version = trim(
            (string) config('inventory_expiry.whatsapp.graph_version'),
            '/'
        );

        $phoneNumberId = (string) config(
            'inventory_expiry.whatsapp.phone_number_id'
        );

        $response = Http::withToken(
            (string) config('inventory_expiry.whatsapp.token')
        )
            ->acceptJson()
            ->asJson()
            ->timeout(
                max(
                    5,
                    (int) config('inventory_expiry.whatsapp.timeout', 15)
                )
            )
            ->retry(2, 250)
            ->post(
                "{$baseUrl}/{$version}/{$phoneNumberId}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'فشل إرسال WhatsApp: HTTP '
                . $response->status()
                . ' - '
                . mb_substr($response->body(), 0, 1200)
            );
        }

        return $response->json() ?? [];
    }

    public function normalizePhone(?string $phone): string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return '';
        }

        $hadPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            return ltrim(substr($digits, 2), '0');
        }

        if ($hadPlus) {
            return ltrim($digits, '0');
        }

        if (str_starts_with($digits, '0')) {
            $countryCode = preg_replace(
                '/\D+/',
                '',
                (string) config(
                    'inventory_expiry.whatsapp.default_country_code',
                    '970'
                )
            ) ?: '';

            return $countryCode . ltrim($digits, '0');
        }

        return $digits;
    }
}
