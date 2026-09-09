<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class InventoryExpiryWhatsAppSender
{
    public function send(string $phone, string $message): array
    {
        $driver = (string) config('inventory_expiry.whatsapp.driver', 'log');

        try {
            return match ($driver) {
                'meta' => $this->sendViaMeta($phone, $message),
                'log' => $this->sendToLog($phone, $message),
                'null' => [
                    'success' => false,
                    'error' => 'WhatsApp driver is disabled.',
                ],
                default => throw new RuntimeException(
                    "Unsupported WhatsApp driver [{$driver}]."
                ),
            };
        } catch (Throwable $e) {
            report($e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendViaMeta(string $phone, string $message): array
    {
        $token = trim((string) config('inventory_expiry.whatsapp.token'));
        $phoneNumberId = trim((string) config('inventory_expiry.whatsapp.phone_number_id'));
        $graphVersion = trim((string) config('inventory_expiry.whatsapp.graph_version', 'v23.0'));

        if ($token === '' || $phoneNumberId === '') {
            return [
                'success' => false,
                'error' => 'WhatsApp Meta credentials are not configured.',
            ];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->post(
                "https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => preg_replace('/\D+/', '', $phone) ?: $phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message,
                    ],
                ]
            );

        if (! $response->successful()) {
            return [
                'success' => false,
                'error' => 'Meta WhatsApp HTTP '
                    . $response->status()
                    . ': '
                    . mb_substr($response->body(), 0, 1000),
            ];
        }

        return ['success' => true, 'error' => null];
    }

    private function sendToLog(string $phone, string $message): array
    {
        Log::info('Inventory expiry WhatsApp', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return ['success' => true, 'error' => null];
    }
}
