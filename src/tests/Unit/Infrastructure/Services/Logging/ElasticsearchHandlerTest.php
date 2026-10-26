<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services\Logging;

use App\Infrastructure\Services\Logging\ElasticsearchHandler;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

final class ElasticsearchHandlerTest extends TestCase
{
    public function test_handler_accepts_a_string_level(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'localhost',
            port: 9200,
            user: null,
            password: null,
            index: 'test-logs',
            level: 'warning',
        );

        $this->assertSame(Level::Warning, $handler->getLevel());
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
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'Test error message',
            context: ['key' => 'value'],
            extra: [],
        );

        $this->expectNotToPerformAssertions();

        // This should not throw an exception even when Elasticsearch is unavailable
        $handler->handle($record);
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
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'Error message',
        );

        $debugRecord = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Debug,
            message: 'Debug message',
        );

        // Error is higher than Warning, should be handled
        $this->assertTrue($handler->isHandling($errorRecord));

        // Debug is lower than Warning, should not be handled
        $this->assertFalse($handler->isHandling($debugRecord));
    }

    public function test_handler_works_without_context_provider(): void
    {
        $handler = new ElasticsearchHandler(
            host: 'non-existent-host',
            port: 9999,
            user: null,
            password: null,
            index: 'test-logs',
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: 'Test error message',
        );

        $this->expectNotToPerformAssertions();

        // Should not throw even without context provider
        $handler->handle($record);
    }
}
