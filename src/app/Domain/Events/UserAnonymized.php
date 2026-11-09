<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * An account was anonymized: its personal data is gone and every role was taken away. Dispatched
 * after the change commits, so a module that mirrors accounts elsewhere can anonymize its copy
 * at once.
 *
 * The roles taken away do not come as a separate UserRolesChanged.
 */
final readonly class UserAnonymized
{
    public function __construct(
        public string $userId,
    ) {}
}
