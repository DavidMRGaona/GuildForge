<?php

declare(strict_types=1);

namespace App\Domain\WebPush;

/**
 * What a push service made of one message (core#803).
 */
enum WebPushDeliveryOutcome: string
{
    /** Accepted: the browser gets it when it is online. */
    case Delivered = 'delivered';

    /** 404 or 410: the browser dropped the subscription, which is deleted. */
    case Gone = 'gone';

    /** 429, a 5xx or no answer: tried again later. */
    case RetryLater = 'retry_later';

    /** Refused for good (a malformed message, a key the service rejects): logged, not retried. */
    case Failed = 'failed';
}
