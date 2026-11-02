<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

/**
 * Runs search:sync in a fresh PHP process.
 *
 * A process that just enabled a module still has the previous set of module providers
 * loaded, so its source registry lacks the new module's sources.
 */
interface SearchSyncRunnerInterface
{
    /**
     * Never throws: a failure is logged, and the nightly search:sync catches up.
     */
    public function sync(?string $type = null): void;
}
