<?php

declare(strict_types=1);

namespace App\Application\Publications\DTOs;

use InvalidArgumentException;

/**
 * A publication a user takes part in, and whether they lead it.
 */
final readonly class PublicationMembershipDTO
{
    public function __construct(
        public string $type,
        public string $id,
        public bool $isLeader,
    ) {
        if (trim($type) === '' || trim($id) === '') {
            throw new InvalidArgumentException('A publication membership needs the type and the id.');
        }
    }
}
