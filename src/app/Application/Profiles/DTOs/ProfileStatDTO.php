<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * What a stat counted: a whole number of zero or more and, optionally, where it links to (an absolute
 * `https` address or a path starting with a single `/`, the rule of site block links). A negative
 * value or a link that rule refuses leaves the stat out.
 */
final readonly class ProfileStatDTO
{
    public function __construct(
        public int $value,
        public ?string $url = null,
    ) {}
}
