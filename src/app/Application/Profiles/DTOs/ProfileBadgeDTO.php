<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

use App\Domain\Profiles\ProfileBadgeColour;

/**
 * A featured badge of a public profile (core#776), as a source gives it and as the browser draws it.
 * Drawn with its image when it has one, else with its icon on its colour; the label is its name for
 * screen readers too, and the description can be read with a keyboard, not only by hovering.
 *
 * The core checks every badge a source gives before anybody sees it and leaves out, with a warning
 * once a day, one whose key is not `<module>.<name>` of the source's own module (the rules of a
 * profile section key), whose label is empty, whose icon is not in ProfileBadgeIcons::NAMES, whose
 * image is not an `https` address or whose link is refused by the rule of site block links (an
 * absolute `https` address or a path of the site). Label and description are plain text, never
 * HTML: control characters are dropped, runs of blanks become one space and they are cut to
 * LABEL_MAX_LENGTH and DESCRIPTION_MAX_LENGTH characters.
 */
final readonly class ProfileBadgeDTO
{
    public const int LABEL_MAX_LENGTH = 60;

    public const int DESCRIPTION_MAX_LENGTH = 200;

    /**
     * @param  string  $key  `<module>.<badge>`, unique among the badges of one account
     * @param  string  $label  the badge's name, already translated
     * @param  string  $description  what it was given for, already translated; may be empty
     * @param  string  $icon  one of ProfileBadgeIcons::NAMES
     * @param  string|null  $imageUrl  an `https` image, drawn cropped to a square instead of the icon
     * @param  string|null  $url  where the badge links to: an absolute `https` address or a path of the site
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $icon,
        public ProfileBadgeColour $colour,
        public ?string $imageUrl = null,
        public ?string $url = null,
    ) {}

    /**
     * What the browser reads (`ProfileBadge` in `@/types/profileSections`).
     *
     * @return array{key: string, label: string, description: string, icon: string, colour: string, imageUrl: string|null, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'colour' => $this->colour->value,
            'imageUrl' => $this->imageUrl,
            'url' => $this->url,
        ];
    }
}
