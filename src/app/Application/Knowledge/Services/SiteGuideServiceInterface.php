<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\SiteGuideDTO;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * The site's own help guides, written in the admin panel. They live next to the official ones
 * (source `site`); guide files never touch them.
 */
interface SiteGuideServiceInterface
{
    /**
     * Stores a new guide and returns its id (`site.<slug of the title>`), which never changes afterwards.
     *
     * @throws SiteGuideException
     */
    public function create(SiteGuideDTO $guide): GuideId;

    /**
     * @throws SiteGuideException when the id is not one of the site's guides or the data is invalid
     */
    public function update(GuideId $id, SiteGuideDTO $guide): void;

    /**
     * Deletes the guide with its aliases, overrides and votes.
     *
     * @throws SiteGuideException when the id is not one of the site's guides
     */
    public function delete(GuideId $id): void;
}
