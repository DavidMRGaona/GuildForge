<?php

declare(strict_types=1);

namespace App\Application\Profiles\Contracts;

use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A counter of the public profile (core#742): messages written, tables run… Apart from sections, so a
 * counter does not vanish because its list is turned off, nor the other way round. A module returns
 * its classes from registerProfileStats(); each one is built from the container again in every request
 * or job that draws profiles.
 *
 * The registry leaves a stat out (with a warning) when its key is invalid, not its owner's or shared
 * with another stat. Drawing it fails closed like a section.
 */
interface ProfileStatInterface
{
    /**
     * Same rules as a section key (`<module>.<stat>`, `core.<stat>`). Stored in the owners' layouts,
     * so it never changes once offered.
     */
    public function key(): string;

    /**
     * The name shown beside the number and in the profile editor, already translated.
     */
    public function label(): string;

    /**
     * The default position among the stats, lowest first; the owner may reorder.
     */
    public function order(): int;

    /**
     * Whether the stat shows on a profile whose owner never turned it on or off.
     */
    public function enabledByDefault(): bool;

    /**
     * Whether the stat can be offered and drawn now. Asked on every profile, so keep it cheap; when
     * it throws the stat is left out.
     */
    public function isAvailable(): bool;

    /**
     * The count for this person (null for a guest): only what they may see, with the same access
     * service as the page the things live on. Null when there is nothing to show, never a misleading
     * 0 for things the person cannot see. If this throws, only the exception class is logged.
     */
    public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO;
}
