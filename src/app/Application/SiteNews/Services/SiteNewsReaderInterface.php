<?php

declare(strict_types=1);

namespace App\Application\SiteNews\Services;

use App\Application\SiteNews\DTOs\SiteNewsItemDTO;
use App\Domain\SiteNews\SiteNewsKind;

/**
 * Reads core content for its news notice, as it is now.
 */
interface SiteNewsReaderInterface
{
    /**
     * The content while it is published; null when it does not exist or is not published.
     */
    public function find(SiteNewsKind $kind, string $id): ?SiteNewsItemDTO;
}
