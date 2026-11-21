<?php

declare(strict_types=1);

namespace App\Application\Activity\Services;

use App\Application\Activity\DTOs\ActivitySourceRegistrationDTO;

/**
 * The activity sources the core and the enabled modules offer (core#774). A singleton filled while
 * the modules boot; it keeps classes, never instances. A class that cannot be built, is not an
 * ActivitySourceInterface, has an invalid type, one that does not start with its owner, or one
 * another class also has, is left out with a warning (once a day per class and reason); two with
 * one type are both left out.
 */
interface ActivitySourceRegistryInterface
{
    /**
     * @param  string  $class  class or container id of an ActivitySourceInterface
     * @param  string|null  $module  owner; null for the core
     */
    public function register(string $class, ?string $module = null): void;

    /**
     * @param  array<string>  $classes
     */
    public function registerMany(array $classes, ?string $module = null): void;

    public function unregisterModule(string $module): void;

    /**
     * Sorted by type.
     *
     * @return list<ActivitySourceRegistrationDTO>
     */
    public function all(): array;

    public function find(string $type): ?ActivitySourceRegistrationDTO;
}
