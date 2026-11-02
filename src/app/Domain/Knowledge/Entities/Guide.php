<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Entities;

use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;
use DateTimeImmutable;

final readonly class Guide
{
    /**
     * @param  list<string>  $keywords
     * @param  list<GuideId>  $related
     */
    public function __construct(
        public GuideId $id,
        public GuideSource $source,
        public ?string $module,
        public string $locale,
        public GuideAudience $audience,
        public string $categoryId,
        public int $order,
        public string $title,
        public string $summary,
        public array $keywords,
        public GuideBodyFormat $bodyFormat,
        public string $body,
        public string $bodyText,
        public GuideConditions $conditions,
        public array $related,
        public bool $featured,
        public bool $hidden,
        public ?DateTimeImmutable $firstSeenAt,
        public ?DateTimeImmutable $contentChangedAt,
    ) {}
}
