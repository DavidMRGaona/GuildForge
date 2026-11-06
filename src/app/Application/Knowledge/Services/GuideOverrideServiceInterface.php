<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * How this site shows an official guide (core or module) without editing it.
 */
interface GuideOverrideServiceInterface
{
    /**
     * A hidden guide is not shown to anybody, its contextual help links included.
     *
     * @throws SiteGuideException when the id is not an official guide
     */
    public function setHidden(GuideId $id, bool $hidden): void;

    /**
     * true stars the guide, false unstars it and null keeps what its file says.
     *
     * @throws SiteGuideException when the id is not an official guide
     */
    public function setFeatured(GuideId $id, ?bool $featured): void;
}
