<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Sections;

use App\Application\Exceptions\NothingToDrawException;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Domain\Profiles\ProfileBioText;
use App\Domain\Profiles\ProfileRegion;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * «Sobre mí»: what the owner wrote about themself, in the narrow column (core#742). Plain text: the
 * component gets it cut into lines and links (ProfileBioText) and escapes every piece, so nothing the
 * owner wrote is ever drawn as HTML. Whoever may see the profile may read it.
 */
final readonly class AboutProfileSection implements ProfileSectionInterface
{
    public const string KEY = 'core.about';

    public function __construct(
        private StoredProfiles $profiles,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('profiles.sections.about.label');
    }

    public function description(): string
    {
        return __('profiles.sections.about.description');
    }

    public function component(): string
    {
        return 'components/profileSections/AboutSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Aside;
    }

    public function order(): int
    {
        return 10;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function props(ProfileOwnerDTO $owner, ?Authenticatable $viewer): array
    {
        $lines = ProfileBioText::lines($this->profiles->find($owner->id)->bio ?? '');

        if ($lines === []) {
            throw NothingToDrawException::make();
        }

        return ['lines' => $lines];
    }
}
