<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;

/**
 * The profile stats of the core and the modules (see ProfileContributionRegistry). A stat needs no
 * check beyond its key.
 *
 * @extends ProfileContributionRegistry<ProfileStatInterface>
 */
final class ProfileStatRegistry extends ProfileContributionRegistry implements ProfileStatRegistryInterface
{
    public function find(string $key): ?ProfileStatInterface
    {
        return $this->item($key);
    }

    protected function kind(): string
    {
        return 'stat';
    }

    protected function contract(): string
    {
        return ProfileStatInterface::class;
    }

    protected function refusalOf(object $item): ?string
    {
        return null;
    }

    protected function orderOf(object $item): int
    {
        return $item->order();
    }
}
