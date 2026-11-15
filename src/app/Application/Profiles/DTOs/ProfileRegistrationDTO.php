<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * A valid profile section or stat as the registry keeps it: what it is, never an instance. Whoever
 * draws it builds it from the container in the current request or job (`$container->make($class)`),
 * so no section keeps what it read in an earlier one.
 */
final readonly class ProfileRegistrationDTO
{
    /**
     * @param  string  $class  the class (or container id) to build it from
     * @param  string|null  $module  the module that owns it; null for the core
     * @param  int  $order  its default order, read once when it was checked
     */
    public function __construct(
        public string $key,
        public ?string $module,
        public string $class,
        public int $order,
    ) {}
}
