<?php

declare(strict_types=1);

namespace App\Domain\SiteNotifications\Repositories;

use App\Domain\ValueObjects\UserId;

/**
 * Each person's site switch per mail category. A category without a row shows its notices.
 */
interface SiteNotificationPreferenceRepositoryInterface
{
    /**
     * @return list<string> Keys of the categories whose notices the person turned off on the site.
     */
    public function disabledFor(UserId $userId): array;

    /**
     * Save the given categories (true shows the notices), leaving the others as they are.
     *
     * @param  array<string, bool>  $preferences
     */
    public function save(UserId $userId, array $preferences): void;

    public function deleteForUser(UserId $userId): void;
}
