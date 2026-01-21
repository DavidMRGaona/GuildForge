<?php

declare(strict_types=1);

return [
    'enabled' => env('ELASTICSEARCH_ENABLED', false),
    'host' => env('ELASTICSEARCH_HOST', 'localhost'),
    'port' => (int) env('ELASTICSEARCH_PORT', 9200),
    'user' => env('ELASTICSEARCH_USER'),
    'password' => env('ELASTICSEARCH_PASSWORD'),
    'index' => env('ELASTICSEARCH_INDEX', 'laravel-logs'),
    'ssl' => env('ELASTICSEARCH_SSL', false),
];
