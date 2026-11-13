<?php

declare(strict_types=1);

namespace Tests\Support\Realtime;

use App\Application\Realtime\Contracts\RealtimeChannelInterface;

/**
 * A module channel whose name does not start with the module: it would let anyone grant
 * another module's (or the core's) channel, so it is never registered.
 */
final class UnprefixedRealtimeChannel implements RealtimeChannelInterface
{
    public function name(): string
    {
        return 'core.site-notifications.{user}';
    }

    public function authorize(object $user, array $parameters): bool
    {
        return true;
    }
}
