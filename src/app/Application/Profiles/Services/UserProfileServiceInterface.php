<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileCreationResultDTO;
use App\Domain\Profiles\Exceptions\ProfileHandleUnavailableException;

/**
 * The public profile row of each account (core#740): created with the account, its address following the
 * public name until the owner chooses one, and forgotten when the account is anonymized.
 *
 * The address comes from `display_name` only, never from `name`; without a public name it is random
 * (`miembro-xxxxxx`). An address once used is kept in the history and never given to anybody else.
 */
interface UserProfileServiceInterface
{
    /**
     * Creates the row of an account that has none. Nothing for anonymized or unknown accounts.
     *
     * @return bool whether a row was created
     *
     * @throws ProfileHandleUnavailableException when no free address was found
     */
    public function createFor(string $userId): bool;

    /**
     * After the public name changed: while the owner has not chosen an address, it moves to the one the
     * new name gives, and the previous one goes to the history (and redirects there). Does nothing for an
     * account without a row, with a chosen address, or anonymized.
     *
     * @throws ProfileHandleUnavailableException when no free address was found
     */
    public function followPublicName(string $userId): void;

    /**
     * Anonymizing or deleting the account: deletes its row, releases the banner, and keeps in the history
     * only the hash of every address it used, so they stay reserved but name nobody.
     */
    public function forget(string $userId): void;

    /**
     * Creates the rows missing for accounts not anonymized (deactivated ones included), in chunks.
     * Idempotent; an account whose row cannot be created is logged and counted as failed.
     */
    public function createMissing(bool $dryRun, int $chunk): ProfileCreationResultDTO;
}
