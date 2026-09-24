<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Face attendance provider
    |--------------------------------------------------------------------------
    |
    | FACEIO is the first provider. Keep this configuration provider-neutral
    | so another backend can be introduced later without changing attendance
    | records or employee mappings.
    |
    */
    'provider' => env('ATTENDANCE_FACE_PROVIDER', 'faceio'),

    'faceio' => [
        'public_id' => env('FACEIO_PUBLIC_ID'),
        'webhook_token' => env('FACEIO_WEBHOOK_TOKEN'),
        'script_url' => 'https://cdn.faceio.net/fio.js',
    ],

    /*
     * Production-safe default: a browser result alone is never sufficient
     * to create an attendance punch. A recent signed FACEIO AUTH webhook
     * must exist and can only be consumed once.
     */
    'require_webhook' => (bool) env(
        'FACEIO_REQUIRE_WEBHOOK',
        true
    ),

    'auth_event_ttl_seconds' => (int) env(
        'FACEIO_AUTH_EVENT_TTL_SECONDS',
        120
    ),

    'enroll_event_ttl_seconds' => (int) env(
        'FACEIO_ENROLL_EVENT_TTL_SECONDS',
        600
    ),

    /*
     * Prevent an accidental second scan immediately after check-in from
     * becoming a check-out.
     */
    'minimum_checkout_gap_seconds' => (int) env(
        'FACE_ATTENDANCE_MIN_CHECKOUT_GAP_SECONDS',
        60
    ),
];
