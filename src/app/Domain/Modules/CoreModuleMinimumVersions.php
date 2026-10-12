<?php

declare(strict_types=1);

namespace App\Domain\Modules;

/**
 * Oldest version of a module this core can run, when an older release would
 * break on it. The compatibility gate rejects anything below, whatever the
 * module's own requires.core says.
 */
final class CoreModuleMinimumVersions
{
    /** @var array<string, string> Module name => minimum version */
    public const array VERSIONS = [
        // Before 2.2.0 the module relied on the core declaring EventModel::gameTables()
        'game-tables' => '2.2.0',
    ];
}
