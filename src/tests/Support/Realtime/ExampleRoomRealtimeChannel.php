<?php

declare(strict_types=1);

namespace Tests\Support\Realtime;

use App\Application\Realtime\Contracts\RealtimeChannelInterface;

/**
 * A module channel: anyone signed in may listen to the room «open», nobody to any other.
 */
final class ExampleRoomRealtimeChannel implements RealtimeChannelInterface
{
    /** @var list<array<string, string>> */
    public static array $asked = [];

    public function name(): string
    {
        return 'example-module.room.{room}';
    }

    public function authorize(object $user, array $parameters): bool
    {
        self::$asked[] = $parameters;

        return ($parameters['room'] ?? null) === 'open';
    }
}
