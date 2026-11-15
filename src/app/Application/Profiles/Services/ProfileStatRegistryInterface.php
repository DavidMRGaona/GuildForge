<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\Contracts\ProfileStatInterface;

/**
 * The profile stats the core and the enabled modules offer (core#742). It only knows which stats
 * exist and are valid; counting them is ProfileRendererInterface.
 */
interface ProfileStatRegistryInterface
{
    /**
     * Registers a stat class, built from the container the first time a stat is asked for. A class
     * that cannot be built or is not valid (see ProfileStatInterface) is left out with a warning. A
     * class already registered by another owner keeps its first owner (warning).
     *
     * @param  class-string<ProfileStatInterface>|class-string  $statClass
     * @param  string|null  $module  the module that owns it; null for the core
     */
    public function register(string $statClass, ?string $module = null): void;

    /**
     * @param  array<class-string<ProfileStatInterface>|class-string>  $statClasses
     */
    public function registerMany(array $statClasses, ?string $module = null): void;

    /**
     * Forgets the stats of a module (disabled or uninstalled).
     */
    public function unregisterModule(string $module): void;

    /**
     * Every valid stat, by default order (then key), whether or not it is available now.
     *
     * @return list<ProfileStatInterface>
     */
    public function all(): array;

    /**
     * The valid stat with that key, or null.
     */
    public function find(string $key): ?ProfileStatInterface;
}
