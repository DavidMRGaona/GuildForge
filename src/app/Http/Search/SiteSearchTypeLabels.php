<?php

declare(strict_types=1);

namespace App\Http\Search;

/**
 * What people read for one type of result: the tab (plural), the row's chip (singular) and the past tag.
 */
final readonly class SiteSearchTypeLabels
{
    public function __construct(
        public string $label,
        public string $itemLabel,
        public ?string $pastLabel,
    ) {}
}
