<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services\Logging;

use App\Application\Services\LogContextProviderInterface;
use App\Infrastructure\Services\Logging\ElasticsearchHandler;
use App\Infrastructure\Services\Logging\ElasticsearchLogger;
use Monolog\Handler\NullHandler;
use Monolog\Level;
use Tests\TestCase;

final class ElasticsearchLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset config for each test
        config(['elasticsearch.enabled' => true]);
        config(['elasticsearch.host' => 'localhost']);
        config(['elasticsearch.port' => 9200]);
        config(['elasticsearch.user' => null]);
        config(['elasticsearch.password' => null]);
        config(['elasticsearch.index' => 'laravel-logs']);
        config(['elasticsearch.ssl' => false]);
    }

    public function test_factory_uses_the_channel_level_and_defaults_to_debug(): void
    {
        $factory = new ElasticsearchLogger;

        $handler = $factory(['level' => 'error'])->getHandlers()[0];
        $this->assertInstanceOf(ElasticsearchHandler::class, $handler);
        $this->assertSame(Level::Error, $handler->getLevel());

        $handler = $factory([])->getHandlers()[0];
        $this->assertInstanceOf(ElasticsearchHandler::class, $handler);
        $this->assertSame(Level::Debug, $handler->getLevel());
    }

    public function test_factory_returns_null_handler_when_disabled(): void
    {
        config(['elasticsearch.enabled' => false]);

        $factory = new ElasticsearchLogger;

        $logger = $factory([]);

        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(NullHandler::class, $handlers[0]);
    }

    public function test_factory_creates_logger_with_elasticsearch_handler_when_enabled(): void
    {
        config(['elasticsearch.enabled' => true]);
        config(['elasticsearch.host' => 'elasticsearch.example.com']);
        config(['elasticsearch.port' => 9243]);
        config(['elasticsearch.user' => 'elastic']);
        config(['elasticsearch.password' => 'secret']);
        config(['elasticsearch.index' => 'app-logs']);
        config(['elasticsearch.ssl' => true]);

        $factory = new ElasticsearchLogger;

        $logger = $factory([
            'level' => 'error',
        ]);

        $handlers = $logger->getHandlers();

        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(ElasticsearchHandler::class, $handlers[0]);
    }

    public function test_factory_reads_config_from_elasticsearch_config(): void
    {
        config(['elasticsearch.enabled' => true]);
        config(['elasticsearch.host' => 'custom-host']);
        config(['elasticsearch.port' => 9201]);
        config(['elasticsearch.index' => 'custom-index']);

        $factory = new ElasticsearchLogger;

        $logger = $factory([]);

        $this->assertEquals('elasticsearch', $logger->getName());
    }

    public function test_logger_channel_name_is_elasticsearch(): void
    {
        $factory = new ElasticsearchLogger;

        $logger = $factory([]);

        $this->assertEquals('elasticsearch', $logger->getName());
    }

    public function test_factory_accepts_context_provider(): void
    {
        config(['elasticsearch.enabled' => true]);

        $contextProvider = $this->createStub(LogContextProviderInterface::class);
        $factory = new ElasticsearchLogger($contextProvider);

        $logger = $factory([]);

        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(ElasticsearchHandler::class, $handlers[0]);
    }

    public function test_factory_works_without_context_provider(): void
    {
        config(['elasticsearch.enabled' => true]);

        $factory = new ElasticsearchLogger;

        $logger = $factory([]);

        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(ElasticsearchHandler::class, $handlers[0]);
    }
}
