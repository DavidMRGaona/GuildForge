<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Domain\Mail\Enums\MailPauseDuration;
use App\Domain\Mail\ValueObjects\MailPause;

/**
 * Pausing every optional mail of an account for a while, without touching its per-category
 * choices. Mandatory mail is never paused. Whether a pause still runs is decided when asked,
 * comparing its end with the current instant: nothing is scheduled to end it.
 */
interface MailPauseServiceInterface
{
    /**
     * The pause that is running now, or null when there is none or it is over.
     */
    public function current(object $user): ?MailPause;

    /**
     * Start a pause that ends with the display day `duration` away (or never), replacing any
     * earlier one.
     */
    public function pause(object $user, MailPauseDuration $duration): MailPause;

    public function resume(object $user): void;
}
