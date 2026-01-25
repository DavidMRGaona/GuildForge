<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services\Logging;

use App\Application\Services\LogContextProviderInterface;
use App\Infrastructure\Services\Logging\ElasticsearchHandler;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class ElasticsearchHandlerTest extends TestCase
{
    public function test_handler_constructs_without_errors(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'localhost',
            port: 9200,
            user: null,
            password: null,
            index: 'test-logs',
            level: Level::Debug,
        );

        $this->assertInstanceOf(ElasticsearchHandler::class, $handler);
    }

    public function test_handler_constructs_with_authentication(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'elasticsearch.example.com',
            port: 9200,
            user: 'elastic',
            password: 'password123',
            index: 'app-logs',
            level: Level::Info,
        );

        $this->assertInstanceOf(ElasticsearchHandler::class, $handler);
    }

    public function test_handler_constructs_with_ssl_enabled(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'elasticsearch.example.com',
            port: 9243,
            user: 'elastic',
            password: 'password123',
            index: 'app-logs',
            ssl: true,
            level: Level::Info,
        );

        $this->assertInstanceOf(ElasticsearchHandler::class, $handler);
    }

    public function test_handler_constructs_with_string_level(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'localhost',
            port: 9200,
            user: null,
            password: null,
            index: 'test-logs',
            level: 'warning',
        );

        $this->assertInstanceOf(ElasticsearchHandler::class, $handler);
    }

    public function test_handler_gracefully_handles_connection_failures(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'non-existent-host',
            port: 9999,
            user: null,
            password: null,
            index: 'test-logs',
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Test error message',
            context: ['key' => 'value'],
            extra: [],
        );

        // This should not throw an exception even when Elasticsearch is unavailable
        $handler->handle($record);

        // If we reach here, the handler gracefully handled the failure
        $this->assertTrue(true);
    }

    public function test_handler_is_handling_returns_true_for_level(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'localhost',
            port: 9200,
            user: null,
            password: null,
            index: 'test-logs',
            level: Level::Warning,
        );

        $errorRecord = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Error message',
        );

        $debugRecord = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'Debug message',
        );

        // Error is higher than Warning, should be handled
        $this->assertTrue($handler->isHandling($errorRecord));

        // Debug is lower than Warning, should not be handled
        $this->assertFalse($handler->isHandling($debugRecord));
    }

    public function test_handler_constructs_with_context_provider(): void
    {
        /** @var LogContextProviderInterface&MockObject $contextProvider */
        $contextProvider = $this->createMock(LogContextProviderInterface::class);
        $contextProvider->method('getRequestId')->willReturn('test-request-id-123');

        $handler = new ElasticsearchHandler(
            host: 'localhost',
            port: 9200,
            user: null,
            password: null,
            index: 'test-logs',
            contextProvider: $contextProvider,
        );

        $this->assertInstanceOf(ElasticsearchHandler::class, $handler);
    }

    public function test_handler_works_without_context_provider(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'non-existent-host',
            port: 9999,
            user: null,
            password: null,
            index: 'test-logs',
            contextProvider: null,
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Test error message',
        );

        // Should not throw even without context provider
        $handler->handle($record);
        $this->assertTrue(true);
    }
}
