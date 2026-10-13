<?php

declare(strict_types=1);

namespace Tests\Support\Logging;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps every record so a test can assert that nothing was logged.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }
}
