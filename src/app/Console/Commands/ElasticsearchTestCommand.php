<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ElasticsearchTestCommand extends Command
{
    protected $signature = 'elasticsearch:test';

    protected $description = 'Test Elasticsearch connection and send test logs';

    public function handle(): int
    {
        if (! config('elasticsearch.enabled')) {
            $this->warn('Elasticsearch logging is disabled (ELASTICSEARCH_ENABLED=false)');

            return Command::SUCCESS;
        }

        $this->showConfiguration();

        if (! $this->testConnection()) {
            return Command::FAILURE;
        }

        $this->sendTestLogs();

        return Command::SUCCESS;
    }

    private function showConfiguration(): void
    {
        $this->info('Elasticsearch Configuration:');
        $this->table(['Setting', 'Value'], [
            ['Host', config('elasticsearch.host')],
            ['Port', config('elasticsearch.port')],
            ['Index', config('elasticsearch.index')],
            ['SSL', config('elasticsearch.ssl') ? 'Yes' : 'No'],
            ['User', config('elasticsearch.user') ? '***' : '(none)'],
        ]);
    }

    private function testConnection(): bool
    {
        $this->info('Testing connection...');

        try {
            $scheme = config('elasticsearch.ssl') ? 'https' : 'http';
            $builder = ClientBuilder::create()
                ->setHosts(["{$scheme}://".config('elasticsearch.host').':'.config('elasticsearch.port')]);

            if (config('elasticsearch.user') && config('elasticsearch.password')) {
                $builder->setBasicAuthentication(
                    (string) config('elasticsearch.user'),
                    (string) config('elasticsearch.password')
                );
            }

            if (config('elasticsearch.ssl')) {
                $builder->setSSLVerification(true);
            }

            $client = $builder->build();

            /** @var \Elastic\Elasticsearch\Response\Elasticsearch $response */
            $response = $client->info();
            $info = $response->asArray();

            $this->info('Connection successful!');
            $this->line('  Cluster: '.$info['cluster_name']);
            $this->line('  Version: '.$info['version']['number']);

            return true;
        } catch (Throwable $e) {
            $this->error('Connection failed: '.$e->getMessage());

            return false;
        }
    }

    private function sendTestLogs(): void
    {
        $this->info('Sending test logs...');

        $channel = Log::channel('elasticsearch');
        $context = ['test' => true, 'command' => 'elasticsearch:test'];

        $channel->debug('Test DEBUG log', $context);
        $channel->info('Test INFO log', $context);
        $channel->notice('Test NOTICE log', $context);
        $channel->warning('Test WARNING log', $context);
        $channel->error('Test ERROR log', $context);
        $channel->critical('Test CRITICAL log', $context);
        $channel->alert('Test ALERT log', $context);
        $channel->emergency('Test EMERGENCY log', $context);

        $this->info('Test logs sent to index: '.config('elasticsearch.index'));
    }
}
