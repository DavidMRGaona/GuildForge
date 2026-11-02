<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\ValueObjects\GuideConditions;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * Builds guide entities for search tests.
 */
final class KnowledgeGuides
{
    /**
     * @param  list<string>  $keywords
     */
    public static function make(
        string $id,
        string $title = 'Un título',
        string $summary = 'Resumen',
        array $keywords = [],
        string $bodyText = 'Cuerpo.',
        int $order = 10,
        string $categoryId = 'getting-started',
        string $locale = 'es',
        GuideAudience $audience = GuideAudience::Site,
    ): Guide {
        return new Guide(
            id: GuideId::fromString($id),
            source: GuideSource::Core,
            module: null,
            locale: $locale,
            audience: $audience,
            categoryId: $categoryId,
            order: $order,
            title: $title,
            summary: $summary,
            keywords: $keywords,
            bodyFormat: GuideBodyFormat::Markdown,
            body: $bodyText,
            bodyText: $bodyText,
            conditions: new GuideConditions,
            related: [],
            featured: false,
            hidden: false,
            firstSeenAt: null,
            contentChangedAt: null,
        );
    }
}
