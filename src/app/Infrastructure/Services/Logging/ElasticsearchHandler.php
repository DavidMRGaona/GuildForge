<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Logging;

use App\Application\Services\LogContextProviderInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

final class ElasticsearchHandler extends AbstractProcessingHandler
{
    private ?Client $client = null;

    private static bool $isLoggingFallback = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly ?string $user,
        private readonly ?string $password,
        private readonly string $index,
        private readonly bool $ssl = false,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        private readonly ?LogContextProviderInterface $contextProvider = null,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $client = $this->getClient();

            $client->index([
                'index' => $this->index,
                'body' => [
                    '@timestamp' => $record->datetime->format('c'),
                    'level' => $record->level->name,
                    'level_code' => $record->level->value,
                    'channel' => $record->channel,
                    'message' => $record->message,
                    'context' => $record->context,
                    'extra' => $record->extra,
                    'app_name' => config('app.name'),
                    'environment' => config('app.env'),
                    'hostname' => gethostname(),
                    'request_id' => $this->contextProvider?->getRequestId(),
                ],
            ]);
        } catch (Throwable $e) {
            $this->logFallback($e, $record);
        }
    }

    private function logFallback(Throwable $e, LogRecord $record): void
    {
        if (self::$isLoggingFallback) {
            return;
        }

        self::$isLoggingFallback = true;

        try {
            Log::channel('daily')->error('Elasticsearch logging failed', [
                'error' => $e->getMessage(),
                'original_channel' => $record->channel,
                'original_level' => $record->level->name,
                'original_message' => $record->message,
            ]);
        } finally {
            self::$isLoggingFallback = false;
        }
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $scheme = $this->ssl ? 'https' : 'http';
            $builder = ClientBuilder::create()
                ->setHosts(["{$scheme}://{$this->host}:{$this->port}"]);

            if ($this->user !== null && $this->password !== null) {
                $builder->setBasicAuthentication($this->user, $this->password);
            }

            if ($this->ssl) {
                $builder->setSSLVerification(true);
            }

            $this->client = $builder->build();
        }

        return $this->client;
    }
}
