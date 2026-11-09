<?php

declare(strict_types=1);

namespace App\Domain\Mail\Repositories;

use App\Domain\ValueObjects\UserId;

/**
 * Each person's choice per mail category. A category without a row is enabled.
 */
interface MailPreferenceRepositoryInterface
{
    /**
     * @return list<string> Keys of the categories the person turned off.
     */
    public function disabledFor(UserId $userId): array;

    /**
     * Save the given categories (key => enabled), leaving the others as they are.
     *
     * @param  array<string, bool>  $preferences
     */
    public function save(UserId $userId, array $preferences): void;

    /**
     * Forget every mail choice of the person: category preferences and the pause.
     */
    public function deleteForUser(UserId $userId): void;
}
