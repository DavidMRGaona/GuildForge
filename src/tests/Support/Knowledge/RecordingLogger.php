<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps every log record in memory so tests can inspect them.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param  array<array-key, mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
