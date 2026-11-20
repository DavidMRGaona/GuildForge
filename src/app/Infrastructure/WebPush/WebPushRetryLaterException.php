<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use RuntimeException;

/**
 * The push service was busy or out of reach: the job is tried again after its backoff.
 * Carries no endpoint, so a failed job never stores one.
 */
final class WebPushRetryLaterException extends RuntimeException
{
    public static function make(): self
    {
        return new self('The push service did not take the message; trying again later.');
    }
}
