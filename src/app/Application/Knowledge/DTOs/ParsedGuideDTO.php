<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;

final readonly class ParsedGuideDTO
{
    /**
     * @param  list<GuideId>  $previousIds
     * @param  list<string>  $keywords
     * @param  list<GuideId>  $related
     */
    public function __construct(
        public GuideId $id,
        public array $previousIds,
        public string $title,
        public string $summary,
        public string $categoryId,
        public GuideAudience $audience,
        public int $order,
        public array $keywords,
        public array $related,
        public GuideConditions $conditions,
        public bool $featured,
        public string $body,
        public string $contentHash,
    ) {}
}
