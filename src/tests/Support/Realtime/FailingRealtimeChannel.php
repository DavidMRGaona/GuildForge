<?php

declare(strict_types=1);

namespace Tests\Support\Realtime;

use App\Application\Realtime\Contracts\RealtimeChannelInterface;
use RuntimeException;

/**
 * A module channel whose check breaks (the database is down, a bug).
 */
final class FailingRealtimeChannel implements RealtimeChannelInterface
{
    public function name(): string
    {
        return 'example-module.broken.{id}';
    }

    public function authorize(object $user, array $parameters): bool
    {
        throw new RuntimeException('The check failed');
    }
}
