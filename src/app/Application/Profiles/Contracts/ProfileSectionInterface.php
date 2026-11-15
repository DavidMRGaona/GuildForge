<?php

declare(strict_types=1);

namespace App\Application\Profiles\Contracts;

use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException;
use App\Domain\Profiles\ProfileRegion;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A section of the public profile (core#742): a list, a summary or a text about the owner, drawn in
 * the wide column or the narrow one. The core registers its own (`core.about`); a module returns its
 * classes from registerProfileSections(). Each one is built from the container, so it may take
 * dependencies.
 *
 * The registry leaves a section out (with a warning) when its key is invalid or not its owner's, its
 * component path is invalid, its region is not a ProfileRegion, or another section has the same key.
 * Drawing it fails closed: turned off by the owner, isAvailable() false or throwing, props() throwing
 * or not fit for the browser all leave the section (title included) out of the page.
 *
 * What a section shows is decided for the person viewing, with the same access service as the page
 * the data comes from: never anything private about the owner.
 */
interface ProfileSectionInterface
{
    /**
     * `core.<section>` for the core, `<module>.<section>` for a module (its own name only). Stored in
     * the owners' layouts, so it never changes once offered.
     */
    public function key(): string;

    /**
     * The section's title on the profile and its name in the profile editor, already translated.
     */
    public function label(): string;

    /**
     * One line for the profile editor saying what the section shows, already translated.
     */
    public function description(): string;

    /**
     * The Vue component that draws the section's content, relative to the owner's `resources/js`
     * and under `components/` (e.g. `components/LatestPostsSection.vue`), resolved like a module
     * slot. Core sections live in `components/profileSections/`. The title is drawn by the page.
     */
    public function component(): string;

    /**
     * The column the section goes in: Main (wide) or Aside (narrow).
     */
    public function region(): ProfileRegion;

    /**
     * The default position within its region, lowest first; the owner may reorder.
     */
    public function order(): int;

    /**
     * Whether the section shows on a profile whose owner never turned it on or off.
     */
    public function enabledByDefault(): bool;

    /**
     * Whether the section can be offered and drawn now. Asked on every profile, so keep it cheap;
     * when it throws the section is left out.
     */
    public function isAvailable(): bool;

    /**
     * The props for the component about this owner, for this person (null for a guest): only what
     * they may see. A string-keyed array that survives json_encode(); anything else leaves the
     * section out. Throw NothingToDrawException when there is nothing to show, so the title is not
     * drawn alone. If this throws anything else, only the exception class is logged.
     *
     * @return array<string, mixed>
     *
     * @throws NothingToDrawException
     */
    public function props(ProfileOwnerDTO $owner, ?Authenticatable $viewer): array;
}
