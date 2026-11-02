<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Repositories;

use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Entities\KnowledgeCategory;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;

interface GuideRepositoryInterface
{
    /**
     * Active guides for an audience and locale, hidden ones included (`hidden` = true).
     *
     * @return list<Guide>
     */
    public function allActive(GuideAudience $audience, string $locale): array;

    public function findActive(GuideId $id, string $locale): ?Guide;

    /**
     * Resolve a previous id to the current one, if it was renamed.
     */
    public function resolveAlias(GuideId $alias): ?GuideId;

    /**
     * @return list<KnowledgeCategory>
     */
    public function categories(GuideAudience $audience): array;

    public function hasLocale(string $locale): bool;
}
