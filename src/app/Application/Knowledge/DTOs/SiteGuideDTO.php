<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * What the panel's form gives for one of the site's own guides. The body is the rich editor's HTML.
 */
final readonly class SiteGuideDTO
{
    /**
     * @param  list<string>  $keywords
     * @param  list<GuideId>  $related
     */
    public function __construct(
        public string $title,
        public string $summary,
        public GuideAudience $audience,
        public string $categoryId,
        public string $body,
        public array $keywords = [],
        public int $order = 100,
        public GuideConditions $conditions = new GuideConditions,
        public array $related = [],
        public bool $featured = false,
        public bool $published = true,
    ) {}
}
