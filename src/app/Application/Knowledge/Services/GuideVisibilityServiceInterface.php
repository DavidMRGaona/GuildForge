<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Entities\KnowledgeCategory;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * The only decision point for which guides a user may see.
 */
interface GuideVisibilityServiceInterface
{
    /**
     * Active, not hidden guides whose conditions pass, in the current knowledge locale.
     *
     * @return list<Guide>
     */
    public function visibleGuides(object $user, GuideAudience $audience): array;

    public function findVisible(object $user, GuideId $id, GuideAudience $audience): ?Guide;

    /**
     * Only categories with at least one visible guide.
     *
     * @return list<array{category: KnowledgeCategory, guideCount: int}>
     */
    public function visibleCategories(object $user, GuideAudience $audience): array;
}
