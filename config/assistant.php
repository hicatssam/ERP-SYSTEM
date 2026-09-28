<?php

return [
    // The key is only read on the server. Never pass it to a Blade view or browser.
    'api_key' => env('ERP_AI_API_KEY'),
    'model' => env('ERP_AI_MODEL', 'gpt-5-mini'),
];
