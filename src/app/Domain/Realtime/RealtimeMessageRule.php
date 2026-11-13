<?php

declare(strict_types=1);

namespace App\Domain\Realtime;

/**
 * What a real-time message may carry: ids and numbers, never content. A message only says that
 * something happened («message 42 in discussion X»); the page asks for the content through its
 * usual request, which checks permissions again. So a channel granted a moment too long, or a
 * message a listener should not have heard, never shows anything the person cannot see.
 */
final class RealtimeMessageRule
{
    public const int MAX_KEYS = 20;

    public const int MAX_LIST = 100;

    public const int MAX_EVENT_LENGTH = 64;

    private const string KEY = '/^[a-z][A-Za-z0-9]{0,39}$/D';

    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di';

    private const string ULID = '/^[0-9A-HJKMNP-TV-Z]{26}$/D';

    private const string EVENT = '/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/D';

    /**
     * Keys are short camelCase names; values are integers, finite numbers, booleans, null, uuids
     * or ulids, or a list of up to MAX_LIST of those.
     *
     * @param  array<mixed>  $payload
     */
    public static function isValidPayload(array $payload): bool
    {
        if (count($payload) > self::MAX_KEYS || ($payload !== [] && array_is_list($payload))) {
            return false;
        }

        foreach ($payload as $key => $value) {
            if (! is_string($key) || preg_match(self::KEY, $key) !== 1) {
                return false;
            }

            if (is_array($value)) {
                if (! array_is_list($value) || count($value) > self::MAX_LIST) {
                    return false;
                }

                foreach ($value as $item) {
                    if (! self::isScalarId($item)) {
                        return false;
                    }
                }

                continue;
            }

            if (! self::isScalarId($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Event names are exact (never a class name) and cannot pass for the protocol's own events
     * (`pusher:…`) or for what browsers send each other (`client-…`).
     */
    public static function isValidEventName(string $event): bool
    {
        return strlen($event) <= self::MAX_EVENT_LENGTH
            && preg_match(self::EVENT, $event) === 1
            && ! str_starts_with($event, 'client-');
    }

    private static function isScalarId(mixed $value): bool
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => true,
            is_float($value) => is_finite($value),
            is_string($value) => preg_match(self::UUID, $value) === 1 || preg_match(self::ULID, $value) === 1,
            default => false,
        };
    }
}
