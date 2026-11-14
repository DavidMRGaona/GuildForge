<?php

declare(strict_types=1);

namespace App\Application\Impersonation\DTOs;

/**
 * A role an administrator views the site as, as the impersonation log keeps it: the display
 * name is stored too because the role can be renamed or deleted later.
 */
final readonly class ViewedRoleDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $displayName,
    ) {}
}
