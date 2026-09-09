<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsAppGateway implements WhatsAppGateway
{
    public function sendExpiryAlert(
        string $phone,
        array $data
    ): void {
        if (! config('services.whatsapp.enabled')) {
            return;
        }

        $token = config(
            'services.whatsapp.token'
        );

        $phoneNumberId = config(
            'services.whatsapp.phone_number_id'
        );

        $version = config(
            'services.whatsapp.graph_version'
        );

        if (
            blank($token)
            || blank($phoneNumberId)
            || blank($version)
        ) {
            throw new RuntimeException(
                'WhatsApp Cloud API is not configured.'
            );
        }

        $phone = preg_replace(
            '/\D+/',
            '',
            $phone
        );

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->post(
                "https://graph.facebook.com/"
                . "{$version}/{$phoneNumberId}/messages",
                [
                    'messaging_product' => 'whatsapp',

                    'to' => $phone,

                    'type' => 'template',

                    'template' => [
                        'name' => config(
                            'services.whatsapp.template_name'
                        ),

                        'language' => [
                            'code' => config(
                                'services.whatsapp.template_language'
                            ),
                        ],

                        'components' => [
                            [
                                'type' => 'body',

                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $data['product'],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $data['batch'],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $data['location'],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => (string) $data['quantity'],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $data['expiry_date'],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => (string) $data['days_left'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'WhatsApp send failed: '
                . $response->body()
            );
        }
    }
}