<?php

declare(strict_types=1);

namespace App\Domain\Mail\Repositories;

use App\Domain\Mail\Enums\MailDelivery;
use App\Domain\ValueObjects\UserId;

/**
 * Each person's choice per mail category. A category without a row is enabled and sent at once.
 */
interface MailPreferenceRepositoryInterface
{
    /**
     * @return list<string> Keys of the categories the person turned off.
     */
    public function disabledFor(UserId $userId): array;

    /**
     * @return list<string> Keys of the categories the person receives as a daily digest (core#333).
     */
    public function digestFor(UserId $userId): array;

    /**
     * Save the given categories, leaving the others as they are. A boolean is the switch:
     * true means at once, false not at all.
     *
     * @param  array<string, bool|MailDelivery>  $preferences
     */
    public function save(UserId $userId, array $preferences): void;

    /**
     * Forget every mail choice of the person: category preferences, the pause, the things they
     * muted and the notices kept for their daily digest.
     */
    public function deleteForUser(UserId $userId): void;
}
