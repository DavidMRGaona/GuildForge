<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * Passes every call to the real service and counts, per guide id, the calls to findVisible().
 */
final class CountingGuideVisibility implements GuideVisibilityServiceInterface
{
    /** @var array<string, int> */
    private array $checks = [];

    public function __construct(
        private readonly GuideVisibilityServiceInterface $inner,
    ) {}

    public function checks(string $guideId): int
    {
        return $this->checks[$guideId] ?? 0;
    }

    public function visibleGuides(object $user, GuideAudience $audience): array
    {
        return $this->inner->visibleGuides($user, $audience);
    }

    public function findVisible(object $user, GuideId $id, GuideAudience $audience): ?Guide
    {
        $this->checks[$id->value()] = $this->checks($id->value()) + 1;

        return $this->inner->findVisible($user, $id, $audience);
    }

    public function visibleCategories(object $user, GuideAudience $audience): array
    {
        return $this->inner->visibleCategories($user, $audience);
    }
}
