<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Cloud API
    |--------------------------------------------------------------------------
    |
    | Credentials remain outside SystemSetting because they are secrets.
    | Configure them in .env, then run: php artisan config:clear
    |
    */
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '970'),
        'timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 15),
    ],
];
