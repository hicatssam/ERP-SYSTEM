<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'language_code' => env('WHATSAPP_LANGUAGE_CODE', 'ar'),
        'expiry_template' => env(
            'WHATSAPP_EXPIRY_TEMPLATE',
            'inventory_expiry_alert'
        ),
        'default_country_code' => env(
            'WHATSAPP_DEFAULT_COUNTRY_CODE',
            '970'
        ),
        'timeout' => env('WHATSAPP_TIMEOUT', 15),
        'retries' => env('WHATSAPP_RETRIES', 2),
    ],

];