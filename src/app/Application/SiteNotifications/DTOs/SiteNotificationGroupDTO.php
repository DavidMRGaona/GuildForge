<?php

declare(strict_types=1);

namespace App\Application\SiteNotifications\DTOs;

/**
 * The unread notices gathered under one group key, as the grouped title sees them: «Ana y 3
 * más respondieron en…».
 */
final readonly class SiteNotificationGroupDTO
{
    /**
     * @param  int  $count  Notices in the group, the new one included (at least 1).
     * @param  int  $otherActors  Distinct people behind them other than the newest one; without actor ids, $count - 1.
     */
    public function __construct(
        public int $count,
        public int $otherActors,
    ) {}
}
