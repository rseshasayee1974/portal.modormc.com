<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reports Configuration Thresholds
    |--------------------------------------------------------------------------
    |
    | Here you can configure the threshold limits for report generation.
    |
    */

    // Number of rows above which Excel generation is queued asynchronously
    'export_queue_threshold' => env('REPORTS_EXPORT_QUEUE_THRESHOLD', 10000),

    // Maximum number of rows allowed for direct synchronous PDF download
    'pdf_max_limit' => env('REPORTS_PDF_MAX_LIMIT', 1000),
];
