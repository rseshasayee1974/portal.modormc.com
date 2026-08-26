<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
      'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'sarvam' => [
        'key' => env('SARVAM_API_KEY'),
        'api_key' => env('SARVAM_API_KEY'),
    ],

    'perione' => [
        'environment' => env('PERIONE_ENV', env('APP_ENV', 'sandbox')),
        'client_id' => env('PERIONE_CLIENT_ID', 'PEINVSb3aadf99327e3ca03792510397d3136b'),
        'client_secret' => env('PERIONE_CLIENT_SECRET', 'PEINVS21f24a6a2291dd214d0d81bf23ae8ec7'),
        'base_url' => env('PERIONE_BASE_URL', 'https://staging.perione.in'),
        'email' => env('PERIONE_EMAIL', 'sayee@onemodo.com'),
        'ip' => env('PERIONE_IP', '192.168.1.98'),

        // Sandbox / Staging
        'sandbox_base_url' => env('PERIONE_SANDBOX_BASE_URL', 'https://staging.perione.in'),
        'sandbox_client_id' => env('PERIONE_SANDBOX_CLIENT_ID', 'PEINVSb3aadf99327e3ca03792510397d3136b'),
        'sandbox_client_secret' => env('PERIONE_SANDBOX_CLIENT_SECRET', 'PEINVS21f24a6a2291dd214d0d81bf23ae8ec7'),
        'sandbox_email' => env('PERIONE_SANDBOX_EMAIL', 'sayee@onemodo.com'),

        // Production
        'prod_base_url' => env('PERIONE_PROD_BASE_URL', 'https://api.perione.in'),
        'prod_client_id' => env('PERIONE_PROD_CLIENT_ID'),
        'prod_client_secret' => env('PERIONE_PROD_CLIENT_SECRET'),
        'prod_email' => env('PERIONE_PROD_EMAIL'),
    ],

];