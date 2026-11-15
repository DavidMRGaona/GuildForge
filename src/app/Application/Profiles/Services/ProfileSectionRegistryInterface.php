<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\Contracts\ProfileSectionInterface;

/**
 * The profile sections the core and the enabled modules offer (core#742). It only knows which
 * sections exist and are valid; whether one is available now, and drawing it, is
 * ProfileRendererInterface.
 */
interface ProfileSectionRegistryInterface
{
    /**
     * Registers a section class, built from the container the first time a section is asked for. A
     * class that cannot be built or is not valid (see ProfileSectionInterface) is left out with a
     * warning. A class already registered by another owner keeps its first owner (warning).
     *
     * @param  class-string<ProfileSectionInterface>|class-string  $sectionClass
     * @param  string|null  $module  the module that owns it; null for the core
     */
    public function register(string $sectionClass, ?string $module = null): void;

    /**
     * @param  array<class-string<ProfileSectionInterface>|class-string>  $sectionClasses
     */
    public function registerMany(array $sectionClasses, ?string $module = null): void;

    /**
     * Forgets the sections of a module (disabled or uninstalled).
     */
    public function unregisterModule(string $module): void;

    /**
     * Every valid section, by default order (then key), whether or not it is available now.
     *
     * @return list<ProfileSectionInterface>
     */
    public function all(): array;

    /**
     * The valid section with that key, or null.
     */
    public function find(string $key): ?ProfileSectionInterface;

    /**
     * The module that owns a valid section, or null for the core's (and for an unknown key).
     */
    public function moduleOf(string $key): ?string;
}
