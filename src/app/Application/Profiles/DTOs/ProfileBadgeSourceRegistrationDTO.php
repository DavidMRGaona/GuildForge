<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * A profile badge source as the registry keeps it (core#776): what to build it from and whose it is,
 * never an instance, so nothing a source read in one request reaches the next.
 */
final readonly class ProfileBadgeSourceRegistrationDTO
{
    /**
     * @param  string  $class  the class (or container id) to build it from
     * @param  string|null  $module  the module that owns it, whose name its badge keys start with; null for the core
     */
    public function __construct(
        public string $class,
        public ?string $module,
    ) {}
}
