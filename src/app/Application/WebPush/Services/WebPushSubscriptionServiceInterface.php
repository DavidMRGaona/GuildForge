<?php

declare(strict_types=1);

namespace App\Application\WebPush\Services;

use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\DTOs\WebPushSubscriptionInputDTO;
use DateTimeInterface;

/**
 * The browsers of each account that receive push messages (core#803). The browser's keys are
 * stored encrypted; one row per endpoint, which follows the last account that subscribed it.
 */
interface WebPushSubscriptionServiceInterface
{
    /** Session key holding the subscription this browser made, forgotten when it signs out. */
    public const string SESSION_KEY = 'web_push_subscription';

    /**
     * Stores or refreshes this browser for the account (the endpoint was validated); returns its id.
     * A refresh counts as a use, so a browser that keeps coming back is never pruned.
     */
    public function subscribe(string $userId, WebPushSubscriptionInputDTO $input): string;

    /** Forgets the account's subscription with that endpoint; false when it has none. */
    public function unsubscribe(string $userId, string $endpoint): bool;

    /** Forgets one subscription, only when it belongs to the account. */
    public function forgetForUser(string $userId, string $subscriptionId): void;

    /** Forgets one subscription, whoever it belongs to (the push service dropped it). */
    public function forget(string $subscriptionId): void;

    /** Forgets every subscription of the account (deactivated or anonymized). */
    public function forgetUser(string $userId): void;

    public function find(string $subscriptionId): ?WebPushSubscriptionDTO;

    /**
     * @return list<WebPushSubscriptionDTO> Newest first.
     */
    public function forUser(string $userId): array;

    public function markUsed(string $subscriptionId): void;

    /** Deletes the subscriptions not used since $before (never used: created before it); how many. */
    public function prune(DateTimeInterface $before): int;
}
