<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;

/**
 * The profile sections of the core and the modules (see ProfileContributionRegistry). Beyond the key,
 * a section needs a component path under `components/` and a region() that answers.
 *
 * @extends ProfileContributionRegistry<ProfileSectionInterface>
 */
final class ProfileSectionRegistry extends ProfileContributionRegistry implements ProfileSectionRegistryInterface
{
    /** Relative to the owner's resources/js, under components/, a .vue file, never climbing out */
    public const string COMPONENT_PATTERN = '#^components/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.vue$#';

    protected function kind(): string
    {
        return 'section';
    }

    protected function contract(): string
    {
        return ProfileSectionInterface::class;
    }

    protected function refusalOf(object $item): ?string
    {
        if (preg_match(self::COMPONENT_PATTERN, $item->component()) !== 1) {
            return 'component';
        }

        // Read once, so a region() that throws leaves the section out here rather than on every profile
        $item->region();

        return null;
    }

    protected function orderOf(object $item): int
    {
        return $item->order();
    }
}
