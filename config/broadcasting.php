<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used to
    | broadcast events. The "reverb" broadcaster uses Laravel Reverb
    | (first-party, free, no external service needed).
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'reverb' => [
            'driver'   => 'reverb',
            'key'      => env('REVERB_APP_KEY'),
            'secret'   => env('REVERB_APP_SECRET'),
            'app_id'   => env('REVERB_APP_ID'),
            'options'  => [
                'host'   => env('REVERB_HOST', '0.0.0.0'),
                'port'   => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                // Guzzle options — leave empty for defaults
            ],
        ],

        'pusher' => [
            'driver'    => 'pusher',
            'key'       => env('PUSHER_APP_KEY'),
            'secret'    => env('PUSHER_APP_SECRET'),
            'app_id'    => env('PUSHER_APP_ID'),
            'options'   => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host'    => env('PUSHER_HOST'),
                'port'    => env('PUSHER_PORT', 443),
                'scheme'  => env('PUSHER_SCHEME', 'https'),
                'useTLS'  => true,
            ],
        ],

        'log'  => ['driver' => 'log'],
        'null' => ['driver' => 'null'],

    ],

];
