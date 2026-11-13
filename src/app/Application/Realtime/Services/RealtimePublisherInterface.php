<?php

declare(strict_types=1);

namespace App\Application\Realtime\Services;

use App\Domain\Realtime\RealtimeMessageRule;

/**
 * Tells the people listening on a private channel that something happened (core#493).
 *
 * Without real time configured it does nothing, so callers never check: the pages that listen
 * keep their periodic requests.
 */
interface RealtimePublisherInterface
{
    /**
     * Sends a message after the current transaction commits (at once outside one; nothing if it
     * rolls back), through the queue and tried once: a late message is worth nothing, the pages
     * catch up on their own. It never throws: a channel, event or payload that breaks the rules
     * is dropped with a warning, and so is a failure to queue it.
     *
     * @param  string  $channel  a concrete channel without `private-`, e.g. `forum.discussion.<uuid>`
     * @param  string  $event  an exact event name, e.g. `post.created` (RealtimeMessageRule::isValidEventName)
     * @param  array<string, mixed>  $payload  ids and numbers only (RealtimeMessageRule::isValidPayload)
     *
     * @see RealtimeMessageRule
     */
    public function publish(string $channel, string $event, array $payload = []): void;
}
