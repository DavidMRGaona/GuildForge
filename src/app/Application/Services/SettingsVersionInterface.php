<?php

declare(strict_types=1);

namespace App\Application\Services;

/**
 * The version of the settings the current request or queue job reads (core#825).
 *
 * It changes once any write to the settings table commits, so a long-running process can tell
 * whether what it derived from the settings is still current without reading them all again.
 * An interface of its own, so test doubles of SettingsServiceInterface keep working.
 */
interface SettingsVersionInterface
{
    /**
     * An opaque token, read at most once per request or job (the same one get() reads with).
     * Throws when the cache holding it cannot be reached.
     */
    public function current(): string;
}
