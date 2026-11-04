<?php

return [
    'mobile_otp' => [
        'template' => env('MOBILE_WHATSAPP_OTP_TEMPLATE', 'sales_executive_login'),
        'country_code' => env('MOBILE_OTP_COUNTRY_CODE', '91'),
    ],
    'tendigit' => [
        'endpoint' => env('TENDIGIT_ENDPOINT', 'https://login.tendigit.in/api/sendtemplate.php'),
        'license_number' => env('TENDIGIT_LICENSE_NUMBER'),
        'api_key' => env('TENDIGIT_API_KEY', 'OVEfLmX6NtRvMlIsYauJ5KrZq'),
    ],

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

    'einvoice' => [
        'provider' => env('EINVOICE_PROVIDER', 'alankit'),
    ],

    'alankit' => [
        'environment' => env('ALANKIT_ENV', 'auto'),
        'subscription_key' => env('ALANKIT_SUBSCRIPTION_KEY', 'AL6U3z6f85e4y7U1l'),
        'sandbox_auth_url' => env('ALANKIT_SANDBOX_AUTH_URL', 'https://developers.eraahi.com/eInvoiceGateway/eivital/v1.04/auth'),
        'production_auth_url' => env('ALANKIT_PRODUCTION_AUTH_URL', 'https://www.alankitgst.com/eInvoiceGateway/eivital/v1.04/auth'),
        'sandbox_generate_url' => env('ALANKIT_SANDBOX_GENERATE_URL', 'https://developers.eraahi.com/eInvoiceGateway/eicore/v1.03/Invoice'),
        'production_generate_url' => env('ALANKIT_PRODUCTION_GENERATE_URL', 'https://www.alankitgst.com/eInvoiceGateway/eicore/v1.03/Invoice'),
        'sandbox_public_key_path' => env('ALANKIT_SANDBOX_PUBLIC_KEY_PATH', public_path('publickey/einv_sandbox.pem')),
        'production_public_key_path' => env('ALANKIT_PRODUCTION_PUBLIC_KEY_PATH', public_path('publickey/einv_production.pem')),
    ],

    'perione' => [
        'environment' => env('PERIONE_ENV', env('APP_ENV', 'sandbox')),
        'client_id' => env('PERIONE_CLIENT_ID', 'PEINVSb3aadf99327e3ca03792510397d3136b'),
        'client_secret' => env('PERIONE_CLIENT_SECRET', 'PEINVS21f24a6a2291dd214d0d81bf23ae8ec7'),
        'base_url' => env('PERIONE_BASE_URL', 'https://staging.perione.in'),
        'email' => env('PERIONE_EMAIL', 'sayee@onemodo.com'),
        'ip' => env('PERIONE_IP', '192.168.1.98'),
        'username' => env('PERIONE_USERNAME', 'Bluefox'),
        'password' => env('PERIONE_PASSWORD', 'Bluefox@123'),
        'gstin' => env('PERIONE_GSTIN', '29AARFB4347G000'),

        // Sandbox / Staging E-Invoice
        'sandbox_base_url' => env('PERIONE_SANDBOX_BASE_URL', 'https://staging.perione.in'),
        'sandbox_client_id' => env('PERIONE_SANDBOX_CLIENT_ID', 'PEINVSb3aadf99327e3ca03792510397d3136b'),
        'sandbox_client_secret' => env('PERIONE_SANDBOX_CLIENT_SECRET', 'PEINVS21f24a6a2291dd214d0d81bf23ae8ec7'),
        'sandbox_email' => env('PERIONE_SANDBOX_EMAIL', env('PERIONE_EMAIL', 'sayee@onemodo.com')),

        // Sandbox / Staging E-Way Bill
        'sandbox_eway_client_id' => env('PERIONE_SANDBOX_EWAY_CLIENT_ID', 'PEWAYS472417d4a8bc74b10d31d4219c6b343c'),
        'sandbox_eway_client_secret' => env('PERIONE_SANDBOX_EWAY_CLIENT_SECRET', 'PEWAYS5376280bd5bc93b6c6ddf334a88d7a45'),

        // Production E-Invoice
        'prod_base_url' => env('PERIONE_PROD_BASE_URL', 'https://api.perione.in'),
        'prod_client_id' => env('PERIONE_PROD_CLIENT_ID'),
        'prod_client_secret' => env('PERIONE_PROD_CLIENT_SECRET'),
        'prod_email' => env('PERIONE_PROD_EMAIL'),

        // Production E-Way Bill
        'prod_eway_client_id' => env('PERIONE_PROD_EWAY_CLIENT_ID', 'PEWAYPc38f83975b650189fb86e3e5659d30fe'),
        'prod_eway_client_secret' => env('PERIONE_PROD_EWAY_CLIENT_SECRET', 'PEWAYP52608f1eabd22d36e310b3c341177f49'),
    ],

];
