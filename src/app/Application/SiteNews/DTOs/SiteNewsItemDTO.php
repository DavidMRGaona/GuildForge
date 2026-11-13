<?php

declare(strict_types=1);

namespace App\Application\SiteNews\DTOs;

use App\Domain\SiteNews\SiteNewsKind;

/**
 * A published piece of core content as its news notice shows it.
 */
final readonly class SiteNewsItemDTO
{
    /**
     * @param  string  $url  Path of its public page.
     * @param  string|null  $summary  Plain text: the date and place of an event, an excerpt otherwise.
     */
    public function __construct(
        public SiteNewsKind $kind,
        public string $id,
        public string $title,
        public string $url,
        public ?string $summary,
    ) {}
}
