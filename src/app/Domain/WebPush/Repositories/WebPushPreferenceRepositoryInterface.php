<?php

declare(strict_types=1);

namespace App\Domain\WebPush\Repositories;

use App\Domain\ValueObjects\UserId;

/**
 * The categories each person receives as browser push notifications (core#804). A category
 * without a row is not pushed.
 */
interface WebPushPreferenceRepositoryInterface
{
    /**
     * @return list<string> Keys of the categories the person turned on, sorted.
     */
    public function enabledFor(UserId $userId): array;

    /**
     * Turn the given categories on (true) or off (false), leaving the others as they are.
     *
     * @param  array<string, bool>  $preferences
     */
    public function save(UserId $userId, array $preferences): void;

    public function deleteForUser(UserId $userId): void;
}
