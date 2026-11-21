<?php

declare(strict_types=1);

namespace App\Application\Activity\Services;

use App\Application\Activity\DTOs\ActivitySourceRegistrationDTO;

/**
 * The activity sources the core and the enabled modules offer (core#774). A singleton filled while
 * the modules boot; it keeps classes, never instances. A class that is not an ActivitySourceInterface,
 * has an invalid type or one that does not start with its owner is left out for good; two with one
 * type are both left out; a class that throws while it is built or read is left out until it
 * builds again (it is tried again on the next lookup, never remembered). Each is warned once a day
 * per class and reason.
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

    /**
     * Whether a source for the type is registered but cannot be offered now: two classes share it,
     * or one failed to build. While a class fails before its type can be read, every type nobody
     * offers is unavailable, since it may be that one's. False for an offered type and for one no
     * module registers. It answers for the latest lookup: ask it right after find() returned null.
     */
    public function isUnavailable(string $type): bool;
}
