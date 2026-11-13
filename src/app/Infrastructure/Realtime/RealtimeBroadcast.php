<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * One real-time message, already checked by the publisher: a private channel, an exact event name
 * and a payload of ids and numbers. Queued and tried once: by the time a retry ran, the page would
 * have caught up through its own requests.
 */
final class RealtimeBroadcast implements ShouldBroadcast
{
    public int $tries = 1;

    public int $timeout = 15;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $name,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->channel);
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
