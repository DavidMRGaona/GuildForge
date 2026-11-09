<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * An account was deactivated (soft deleted). Dispatched after the change commits; a permanent
 * deletion does not come through here.
 */
final readonly class UserDeactivated
{
    public function __construct(
        public string $userId,
    ) {}
}
