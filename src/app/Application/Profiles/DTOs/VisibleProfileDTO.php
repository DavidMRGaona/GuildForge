<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ValueObjects\ProfileHandle;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A public profile one person may see (core#840), as VisibleProfilesInterface::among() gives it to a
 * module: only what the profile page itself shows that person. Never the account name, the email or
 * the roles.
 *
 * Checked when it is built: the account id is a UUID (kept in lower case), the address has the form
 * of one (`/miembros/<handle>`; its reserved words are not checked again, like the current address
 * when saving), the public name is not blank and has no blanks around it, the avatar is not blank.
 */
final readonly class VisibleProfileDTO
{
    /** The account id, in lower case */
    public string $userId;

    /**
     * @param  string  $handle  the profile's current address: the page is `/miembros/<handle>`
     * @param  string|null  $publicName  as the page shows it; null only on the owner's own profile, while they have none
     * @param  string|null  $avatarPublicId  the avatar's Cloudinary public id, drawn like the page's; null without avatar
     *
     * @throws InvalidArgumentException when a value is not one the profile page would show
     */
    public function __construct(
        string $userId,
        public string $handle,
        public ?string $publicName,
        public ?string $avatarPublicId,
    ) {
        if (! Str::isUuid($userId)) {
            throw new InvalidArgumentException('A visible profile needs an account id.');
        }

        $this->userId = strtolower($userId);

        $length = strlen($handle);
        if ($length < ProfileHandle::MIN_LENGTH || $length > ProfileHandle::MAX_LENGTH || preg_match(ProfileHandle::PATTERN, $handle) !== 1) {
            throw new InvalidArgumentException("The visible profile of account '{$this->userId}' needs an address.");
        }

        if ($publicName !== null && (trim($publicName) === '' || trim($publicName) !== $publicName)) {
            throw new InvalidArgumentException("The public name of visible profile '{$handle}' must be null or trimmed text.");
        }

        if ($avatarPublicId !== null && trim($avatarPublicId) === '') {
            throw new InvalidArgumentException("The avatar of visible profile '{$handle}' must be null or not blank.");
        }
    }
}
