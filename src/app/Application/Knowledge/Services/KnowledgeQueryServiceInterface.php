<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\Response\KnowledgeCategoryPageResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeGuideResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeHomeResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * Read side of the help centre: everything is already filtered by what the user may see.
 */
interface KnowledgeQueryServiceInterface
{
    public function home(object $user, GuideAudience $audience): KnowledgeHomeResponseDTO;

    /**
     * Null when the category does not exist or has no visible guides.
     */
    public function category(object $user, GuideAudience $audience, string $categoryId): ?KnowledgeCategoryPageResponseDTO;

    /**
     * Null when the guide does not exist or the user may not see it.
     */
    public function guide(object $user, GuideAudience $audience, GuideId $id): ?KnowledgeGuideResponseDTO;

    /**
     * @return list<KnowledgeSearchHitDTO>
     */
    public function search(object $user, GuideAudience $audience, string $query, int $limit): array;

    public function resolveAlias(GuideId $id): ?GuideId;

    /**
     * @return list<string>
     */
    public function visibleIds(object $user, GuideAudience $audience): array;
}
