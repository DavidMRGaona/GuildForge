<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * The owner of a public profile as sections and stats see them: the account id (to query their own
 * data), the address and the public name. Never the email, the account name or the roles.
 */
final readonly class ProfileOwnerDTO
{
    public function __construct(
        public string $id,
        public string $handle,
        public ?string $publicName,
    ) {}
}
