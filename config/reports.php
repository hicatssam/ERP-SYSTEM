<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export row caps
    |--------------------------------------------------------------------------
    |
    | Environment variables are read only from config files so these values
    | continue to work after "php artisan config:cache" in production.
    |
    */
    'xlsx_row_cap' => (int) env(
        'EXPORT_XLSX_ROW_CAP',
        10000
    ),

    'pdf_row_cap' => (int) env(
        'EXPORT_PDF_ROW_CAP',
        500
    ),
];
