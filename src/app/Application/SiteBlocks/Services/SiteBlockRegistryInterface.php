<?php

declare(strict_types=1);

namespace App\Application\SiteBlocks\Services;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;

/**
 * The site blocks the core and the enabled modules offer (core#696). It only knows which blocks
 * exist and are valid; whether one is available now, and drawing it, is SiteBlockRendererInterface.
 */
interface SiteBlockRegistryInterface
{
    /**
     * Registers a block class, built from the container the first time a block is asked for. A
     * class that cannot be built or is not valid (see SiteBlockInterface) is left out with a warning.
     *
     * @param  class-string<SiteBlockInterface>|class-string  $blockClass
     * @param  string|null  $module  the module that owns it; null for the core
     */
    public function register(string $blockClass, ?string $module = null): void;

    /**
     * @param  array<class-string<SiteBlockInterface>|class-string>  $blockClasses
     */
    public function registerMany(array $blockClasses, ?string $module = null): void;

    /**
     * Forgets the blocks of a module (disabled or uninstalled).
     */
    public function unregisterModule(string $module): void;

    /**
     * Every valid block, in registration order (the core's first).
     *
     * @return list<SiteBlockInterface>
     */
    public function all(): array;

    /**
     * The valid blocks that may go in a placement, whether or not they are available now.
     *
     * @return list<SiteBlockInterface>
     */
    public function forPlacement(SiteBlockPlacement $placement): array;

    /**
     * The valid block with that key, or null.
     */
    public function find(string $key): ?SiteBlockInterface;
}
