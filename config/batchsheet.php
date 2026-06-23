<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batch Sheet Upload Configuration
    |--------------------------------------------------------------------------
    */

    // Maximum file size in kilobytes (default 20MB)
    'max_file_size' => (int) env('BATCHSHEET_MAX_SIZE', 20480),

    // Storage disk and path
    'storage_disk' => env('BATCHSHEET_DISK', 'public'),
    'storage_path' => 'batch-sheets/uploads',

    // Minimum confidence score (0-100) below which fields are flagged for review
    'confidence_threshold' => (int) env('BATCHSHEET_CONFIDENCE', 75),

    // AI provider for image/scanned PDF extraction: 'gemini' or 'openai'
    'ai_provider' => env('BATCHSHEET_AI_PROVIDER', 'gemini'),

    // Timeout for AI/OCR API calls in seconds
    'ocr_timeout' => (int) env('BATCHSHEET_OCR_TIMEOUT', 60),

    // Maximum concurrent processing jobs
    'max_concurrent_jobs' => (int) env('BATCHSHEET_MAX_JOBS', 5),

    // Allowed MIME types
    'allowed_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
        'image/bmp',
        'image/webp',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain', // some CSVs report as text/plain
    ],

    // Allowed file extensions (lowercase, no dot)
    'allowed_extensions' => [
        'pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'bmp', 'webp',
        'xlsx', 'xls', 'csv',
    ],

    // Blocked extensions (security)
    'blocked_extensions' => [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif',
        'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'ps1',
        'sh', 'bash', 'csh', 'ksh',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
        'dll', 'sys', 'drv',
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps',
        'asp', 'aspx', 'jsp', 'cgi', 'pl', 'py', 'rb',
        'svg', 'html', 'htm', 'xml', 'xsl',
        'docm', 'xlsm', 'pptm', // macro-enabled
    ],

    // Magic bytes for MIME validation (first N bytes → expected MIME)
    'magic_bytes' => [
        '%PDF'                             => 'application/pdf',
        "\xFF\xD8\xFF"                     => 'image/jpeg',
        "\x89PNG\r\n\x1A\n"               => 'image/png',
        "II\x2A\x00"                       => 'image/tiff',    // Little-endian TIFF
        "MM\x00\x2A"                       => 'image/tiff',    // Big-endian TIFF
        "BM"                               => 'image/bmp',
        "RIFF"                             => 'image/webp',    // WebP (RIFF container)
        "PK\x03\x04"                       => 'application/zip', // XLSX is a ZIP
    ],

];
