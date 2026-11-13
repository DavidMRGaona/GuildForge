<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default broadcaster
    |--------------------------------------------------------------------------
    |
    | Real time is optional per installation (core#493). "reverb" turns it on when every REVERB_*
    | setting below is present; "log" or "null" keep it off and the pages poll as before. A
    | "reverb" connection with a missing setting falls back to "null" (RealtimeServiceProvider).
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),

            // Where the application publishes messages: the Reverb process, usually an internal address
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],

            // Where browsers open the WebSocket; each one defaults to the site's own address (APP_URL)
            'public' => [
                'host' => env('REVERB_PUBLIC_HOST'),
                'port' => env('REVERB_PUBLIC_PORT'),
                'scheme' => env('REVERB_PUBLIC_SCHEME'),
            ],

            'client_options' => [
                // A message that cannot be sent is not worth holding the queue worker for
                'timeout' => (int) env('REVERB_PUBLISH_TIMEOUT', 5),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
