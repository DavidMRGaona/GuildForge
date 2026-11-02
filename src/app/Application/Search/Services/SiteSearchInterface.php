<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;

/**
 * The site-wide search: only documents of the requested types and audiences are ever returned.
 */
interface SiteSearchInterface
{
    public function search(SiteSearchQueryDTO $query): SiteSearchResultDTO;
}
