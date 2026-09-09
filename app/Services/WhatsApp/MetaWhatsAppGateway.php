<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsAppGateway implements WhatsAppGateway
{
    public function sendTemplate(
        string $to,
        string $templateName,
        array $bodyParameters = [],
        ?string $languageCode = null
    ): array {
        $this->ensureConfigured();

        $to = $this->normalizePhone($to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                        ?: (string) config('services.whatsapp.language_code', 'ar'),
                ],
            ],
        ];

        if ($bodyParameters !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => collect($bodyParameters)
                    ->map(fn ($value) => [
                        'type' => 'text',
                        'text' => (string) $value,
                    ])
                    ->values()
                    ->all(),
            ]];
        }

        $response = $this->client()->post(
            $this->messagesEndpoint(),
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Meta WhatsApp API error: '
                . $response->status()
                . ' '
                . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken((string) config('services.whatsapp.token'))
            ->timeout((int) config('services.whatsapp.timeout', 15))
            ->retry(
                (int) config('services.whatsapp.retries', 2),
                500,
                throw: false
            );
    }

    private function messagesEndpoint(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            trim((string) config('services.whatsapp.graph_version')),
            trim((string) config('services.whatsapp.phone_number_id'))
        );
    }

    private function ensureConfigured(): void
    {
        if (! config('services.whatsapp.enabled')) {
            throw new RuntimeException('WhatsApp sending is disabled.');
        }

        foreach (['token', 'phone_number_id', 'graph_version'] as $key) {
            if (! filled(config("services.whatsapp.{$key}"))) {
                throw new RuntimeException(
                    "Missing WhatsApp configuration: {$key}"
                );
            }
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if ($phone === '') {
            throw new RuntimeException('Recipient WhatsApp phone is empty.');
        }

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            $countryCode = preg_replace(
                '/\D+/',
                '',
                (string) config(
                    'services.whatsapp.default_country_code',
                    ''
                )
            ) ?? '';

            if ($countryCode === '') {
                throw new RuntimeException(
                    'Local phone detected but WHATSAPP_DEFAULT_COUNTRY_CODE is not configured.'
                );
            }

            $phone = $countryCode . ltrim($phone, '0');
        }

        return $phone;
    }
}
