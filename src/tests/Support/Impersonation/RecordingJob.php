<?php

declare(strict_types=1);

namespace Tests\Support\Impersonation;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A queued job that only remembers it ran: with the sync queue of the tests it runs at once,
 * unless something keeps it from being queued.
 */
final class RecordingJob implements ShouldQueue
{
    use Queueable;

    public static int $handled = 0;

    public function handle(): void
    {
        self::$handled++;
    }
}
