<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileBadgeSourceRegistrationDTO;

/**
 * The profile badge sources of the enabled modules (core#776). It only knows which classes exist and
 * whose they are; asking them is ProfileBadgesInterface, which builds each one in the current
 * request or job.
 */
interface ProfileBadgeSourceRegistryInterface
{
    /**
     * Registers a source class. A class already registered by another owner keeps its first owner
     * (warning). Whether it can be built and is a source is checked when badges are drawn.
     *
     * @param  string  $sourceClass  a class implementing ProfileBadgeSourceInterface, or a container id
     * @param  string|null  $module  the module that owns it; null for the core
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * @param  array<string>  $sourceClasses  classes implementing ProfileBadgeSourceInterface, or container ids
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    /**
     * Forgets the sources of a module (disabled or uninstalled).
     */
    public function unregisterModule(string $module): void;

    /**
     * Every source, in the order they were registered.
     *
     * @return list<ProfileBadgeSourceRegistrationDTO>
     */
    public function all(): array;
}
