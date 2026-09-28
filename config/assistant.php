<?php

$configuredProvider = trim((string) env('ERP_AI_PROVIDER'));
$provider = strtolower($configuredProvider !== ''
    ? $configuredProvider
    : (trim((string) env('GROQ_API_KEY')) !== '' ? 'groq' : 'openai'));

return [
    'provider' => $provider,
    // Keys stay on the server. Never pass them to a Blade view or browser.
    'api_key' => match ($provider) {
        'groq' => env('GROQ_API_KEY'),
        'openai' => env('ERP_AI_API_KEY') ?: env('OPENAI_API_KEY'),
        default => null,
    },
    'model' => $provider === 'groq'
        ? env('ERP_AI_GROQ_MODEL', 'openai/gpt-oss-20b')
        : env('ERP_AI_MODEL', 'gpt-5-mini'),
];
