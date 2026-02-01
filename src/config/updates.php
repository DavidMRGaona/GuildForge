<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Updates system
    |--------------------------------------------------------------------------
    |
    | Configuration for the automatic updates system that handles both
    | core application updates and module updates from GitHub.
    |
    */

    'enabled' => env('UPDATES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Check interval
    |--------------------------------------------------------------------------
    |
    | How often to check for updates in seconds. Default is 1 hour (3600).
    | Set to 0 to disable automatic checks.
    |
    */

    'check_interval' => env('UPDATE_CHECK_INTERVAL', 3600),

    /*
    |--------------------------------------------------------------------------
    | GitHub configuration
    |--------------------------------------------------------------------------
    |
    | GitHub personal access token for API requests. Required in production
    | to avoid rate limiting (60 requests/hour without token vs 5000 with).
    |
    */

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'api_base_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        'timeout' => env('GITHUB_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Core repository
    |--------------------------------------------------------------------------
    |
    | GitHub repository for the core application.
    |
    */

    'core' => [
        'owner' => env('CORE_GITHUB_OWNER', 'DavidMRGaona'),
        'repo' => env('CORE_GITHUB_REPO', 'guildforge'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage paths
    |--------------------------------------------------------------------------
    |
    | Paths for temporary downloads and backups.
    |
    */

    'temp_path' => storage_path('app/temp/updates'),
    'backup_path' => storage_path('app/backups'),

    /*
    |--------------------------------------------------------------------------
    | Backup retention
    |--------------------------------------------------------------------------
    |
    | Number of backups to keep per module. Older backups are automatically
    | deleted when this limit is exceeded.
    |
    */

    'backup_retention' => env('BACKUP_RETENTION', 5),

    /*
    |--------------------------------------------------------------------------
    | Cache settings
    |--------------------------------------------------------------------------
    |
    | Cache TTL for GitHub API results in seconds. Default is 1 hour.
    | This reduces API calls and improves performance.
    |
    */

    'cache' => [
        'ttl' => env('UPDATE_CACHE_TTL', 3600),
        'key_prefix' => 'updates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch checking
    |--------------------------------------------------------------------------
    |
    | When enabled, groups all module update checks into a single operation.
    | Reduces API calls and improves performance.
    |
    */

    'batch_check' => env('UPDATE_BATCH_CHECK', true),

    /*
    |--------------------------------------------------------------------------
    | Notification settings
    |--------------------------------------------------------------------------
    |
    | Configure how and when to notify about available updates.
    |
    */

    'notifications' => [
        'enabled' => env('UPDATE_NOTIFICATIONS_ENABLED', true),
        'email' => env('UPDATE_NOTIFY_EMAIL'),
        'frequency' => env('UPDATE_NOTIFY_FREQUENCY', 'weekly'), // daily, weekly, never
        'notify_admins' => true, // Notify all users with admin role
    ],

    /*
    |--------------------------------------------------------------------------
    | Update behavior
    |--------------------------------------------------------------------------
    |
    | Control how updates are applied.
    |
    */

    'behavior' => [
        // Allow pre-release versions
        'allow_prereleases' => env('UPDATE_ALLOW_PRERELEASES', false),

        // Require checksum verification
        'verify_checksum' => env('UPDATE_VERIFY_CHECKSUM', true),

        // Run health check after update
        'health_check' => env('UPDATE_HEALTH_CHECK', true),

        // Automatic rollback on failure
        'auto_rollback' => env('UPDATE_AUTO_ROLLBACK', true),
    ],
];
