<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Face attendance provider
    |--------------------------------------------------------------------------
    |
    | CompreFace is self-hosted and free/open-source. Laravel talks to it
    | server-to-server; the browser never receives the recognition API key.
    |
    */
    'provider' => env(
        'ATTENDANCE_FACE_PROVIDER',
        'compreface'
    ),

    'compreface' => [
        'base_url' => rtrim(
            (string) env(
                'COMPREFACE_BASE_URL',
                'http://127.0.0.1:8001'
            ),
            '/'
        ),

        'api_key' =>
            env('COMPREFACE_API_KEY'),

        'timeout_seconds' => (int) env(
            'COMPREFACE_TIMEOUT_SECONDS',
            12
        ),

        'det_prob_threshold' => (float) env(
            'COMPREFACE_DET_PROB_THRESHOLD',
            0.80
        ),

        'similarity_threshold' => (float) env(
            'COMPREFACE_SIMILARITY_THRESHOLD',
            0.78
        ),

        /*
         * Basic active liveness:
         * 1) capture a mostly-forward frame
         * 2) ask employee to turn the head clearly to either side
         * 3) CompreFace pose plugin must report enough yaw movement
         */
        'front_max_abs_yaw' => (float) env(
            'COMPREFACE_FRONT_MAX_ABS_YAW',
            15
        ),

        'turned_min_abs_yaw' => (float) env(
            'COMPREFACE_TURNED_MIN_ABS_YAW',
            18
        ),

        'min_yaw_delta' => (float) env(
            'COMPREFACE_MIN_YAW_DELTA',
            14
        ),
    ],

    'challenge_ttl_seconds' => (int) env(
        'FACE_ATTENDANCE_CHALLENGE_TTL_SECONDS',
        90
    ),

    /*
     * Prevent an accidental immediate second scan from becoming check-out.
     */
    'minimum_checkout_gap_seconds' => (int) env(
        'FACE_ATTENDANCE_MIN_CHECKOUT_GAP_SECONDS',
        60
    ),
];
