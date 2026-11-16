<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileCreationResultDTO;
use App\Application\Profiles\DTOs\ProfileUpdateDTO;
use App\Domain\Profiles\Exceptions\InvalidProfileHandleException;
use App\Domain\Profiles\Exceptions\ProfileHandleChangeTooSoonException;
use App\Domain\Profiles\Exceptions\ProfileHandleTakenException;
use App\Domain\Profiles\Exceptions\ProfileHandleUnavailableException;
use App\Domain\Profiles\ProfileHandleAvailability;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * The public profile row of each account (core#740): created with the account, its address following the
 * public name until the owner chooses one, and forgotten when the account is anonymized.
 *
 * The address comes from `display_name` only, never from `name`; without a public name it is random
 * (`miembro-xxxxxx`). Every address given is written to the history in the same transaction, whose
 * primary key keeps it reserved for ever: it is never given to anybody else, also when the owner chooses
 * one in the editor (core#743).
 *
 * Whoever calls the editing methods has already decided that the person is the owner: they only save.
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
     * new name gives, and the previous one is retired (and redirects there). An address that still follows
     * the name (a numbered or random-suffixed form of its slug) is kept. Does nothing for an account
     * without a row, with a chosen address, or anonymized.
     *
     * @return bool whether the address moved
     *
     * @throws ProfileHandleUnavailableException when no free address was found
     */
    public function followPublicName(string $userId): bool;

    /**
     * Anonymizing or deleting the account: deletes its row, releases the banner, and keeps in the history
     * only the hash of every address it used, so they stay reserved but name nobody.
     */
    public function forget(string $userId): void;

    /**
     * Creates the rows missing for accounts not anonymized (deactivated ones included), and moves the
     * addresses not chosen that no longer follow the public name (a failed move when the name changed),
     * in chunks. Idempotent; an account whose row cannot be created or moved is logged and counted as
     * failed.
     */
    public function createMissing(bool $dryRun, int $chunk): ProfileCreationResultDTO;

    /**
     * Whether the owner could choose this address now: their current one, one free for them (never held
     * by anybody else; their own old ones are), or unavailable, which says the same for an address
     * somebody holds, held before, and a reserved word. The 30-day limit is not part of it.
     */
    public function handleAvailability(string $userId, string $handle): ProfileHandleAvailability;

    /**
     * When the owner may choose another address, or null when they may now (or have no profile).
     */
    public function nextHandleChangeAt(string $userId): ?\DateTimeImmutable;

    /**
     * Saves the owner's choices in one transaction. A new address goes through the history like any
     * other (its primary key decides who gets it), marks the address as chosen, so the public name no
     * longer moves it, and starts the 30 days again; the old one is retired and redirects. The layout
     * keeps what it said of keys not offered now. When it throws, nothing is saved.
     *
     * @throws InvalidProfileHandleException when the address is malformed or a reserved word
     * @throws ProfileHandleTakenException when anybody else holds or held the address
     * @throws ProfileHandleChangeTooSoonException when the owner chose an address under 30 days ago
     * @throws RuntimeException when the account has no profile
     */
    public function update(string $userId, ProfileUpdateDTO $update): void;

    /**
     * Stores a new banner (already validated) through the `images` disk under `profiles/banners/<Y>/<m>/`
     * and puts it in place of the previous one, which is released to the media library.
     *
     * @return string the stored path
     *
     * @throws RuntimeException when it cannot be stored or the account has no profile
     */
    public function replaceBanner(string $userId, UploadedFile $banner): string;

    /**
     * Takes the banner away (the accent gradient shows again) and releases it to the media library.
     */
    public function removeBanner(string $userId): void;

    /**
     * Moderation from the panel (core#746): takes away what the owner wrote and uploaded, the «Sobre mí»
     * text and the banner (released to the media library), in one transaction under the row lock. The
     * address, the visibility, the badge and the layout stay as the owner left them. Whoever calls it has
     * already decided that the person may moderate this account.
     *
     * @return bool whether there was anything to take away (false too for an account without a profile)
     */
    public function resetContent(string $userId): bool;
}
