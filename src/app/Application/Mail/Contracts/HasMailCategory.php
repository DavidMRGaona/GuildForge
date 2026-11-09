<?php

declare(strict_types=1);

namespace App\Application\Mail\Contracts;

/**
 * Implemented by every mail notification: the category it belongs to decides whether it
 * reaches a person who turned that kind of mail off.
 */
interface HasMailCategory
{
    /**
     * Key of a registered category, e.g. "game-tables.reminders".
     */
    public function mailCategory(): string;
}
