<?php

declare(strict_types=1);

namespace App\Application\Services;

use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * Rewrites every stored instant from one storage zone to the other (Europe/Madrid wall times to UTC, or back).
 */
interface StorageTimezoneConverterInterface
{
    /** Whether the conversion can run on the current database connection. */
    public function supportsConnection(): bool;

    /**
     * Values a conversion would rewrite: non-null values per "table.column".
     *
     * @return array<string, int>
     */
    public function countValues(): array;

    /**
     * Must run inside the caller's transaction, which also writes the storage zone marker:
     * data and marker change together or not at all.
     *
     * $afterLock runs right after the run-exclusion lock is taken and before any value is
     * rewritten: the caller re-checks there that no other run converted the data meanwhile (and
     * throws if it did).
     *
     * @return array<string, int> Values rewritten per "table.column"
     *
     * @throws InvalidArgumentException When a zone is not a storage zone or both are the same
     * @throws LogicException When the connection is not supported or no transaction is open
     */
    public function convert(string $from, string $to, ?Closure $afterLock = null): array;
}
