<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Logging;

use App\Application\Services\LogContextProviderInterface;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class ElasticsearchLogger
{
    public function __construct(
        private readonly ?LogContextProviderInterface $contextProvider = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        if (! config('elasticsearch.enabled', false)) {
            return new Logger('elasticsearch', [new NullHandler]);
        }

        $handler = new ElasticsearchHandler(
            host: (string) config('elasticsearch.host', 'localhost'),
            port: (int) config('elasticsearch.port', 9200),
            user: config('elasticsearch.user'),
            password: config('elasticsearch.password'),
            index: (string) config('elasticsearch.index', 'laravel-logs'),
            ssl: (bool) config('elasticsearch.ssl', false),
            level: $config['level'] ?? 'debug',
            contextProvider: $this->contextProvider,
        );

        return new Logger('elasticsearch', [$handler]);
    }
}
