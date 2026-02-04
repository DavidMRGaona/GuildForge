<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    |
    | This is the path where your modules are stored. By default, modules
    | are stored in the "modules" directory at the base of your application.
    |
    */

    'path' => env('MODULES_PATH', storage_path('modules')),

    /*
    |--------------------------------------------------------------------------
    | Auto Discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, the module system will automatically discover modules
    | in the configured path on application boot.
    |
    */

    'auto_discovery' => env('MODULES_AUTO_DISCOVERY', false),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Module discovery results can be cached to improve performance.
    | Set the cache driver and TTL (in seconds) here.
    |
    */

    'cache' => [
        'enabled' => env('MODULES_CACHE_ENABLED', false),
        'key' => 'modules.discovered',
        'ttl' => 3600,
    ],
];
