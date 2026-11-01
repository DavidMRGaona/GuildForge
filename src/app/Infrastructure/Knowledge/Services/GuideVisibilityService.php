<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Knowledge\Services\GuideConditionEvaluatorInterface;
use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Application\Knowledge\Services\KnowledgeLocaleResolverInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * Bound `scoped`: results are memoized per user and audience for the request or job.
 */
final class GuideVisibilityService implements GuideVisibilityServiceInterface
{
    /** @var array<string, list<Guide>> */
    private array $visible = [];

    public function __construct(
        private readonly GuideRepositoryInterface $guides,
        private readonly GuideConditionEvaluatorInterface $evaluator,
        private readonly KnowledgeLocaleResolverInterface $locales,
    ) {}

    public function visibleGuides(object $user, GuideAudience $audience): array
    {
        $id = $user->id ?? null;
        if (! is_string($id) && ! is_int($id)) {
            return $this->compute($user, $audience);
        }

        $key = $id.'|'.$audience->value;

        return $this->visible[$key] ??= $this->compute($user, $audience);
    }

    public function findVisible(object $user, GuideId $id, GuideAudience $audience): ?Guide
    {
        foreach ($this->visibleGuides($user, $audience) as $guide) {
            if ($guide->id->value() === $id->value()) {
                return $guide;
            }
        }

        return null;
    }

    public function visibleCategories(object $user, GuideAudience $audience): array
    {
        $counts = [];
        foreach ($this->visibleGuides($user, $audience) as $guide) {
            $counts[$guide->categoryId] = ($counts[$guide->categoryId] ?? 0) + 1;
        }

        $result = [];
        foreach ($this->guides->categories($audience) as $category) {
            $count = $counts[$category->id] ?? 0;
            if ($count > 0) {
                $result[] = ['category' => $category, 'guideCount' => $count];
            }
        }

        return $result;
    }

    /**
     * @return list<Guide>
     */
    private function compute(object $user, GuideAudience $audience): array
    {
        $visible = [];
        foreach ($this->guides->allActive($audience, $this->locales->locale()) as $guide) {
            if ($guide->hidden) {
                continue;
            }
            if ($this->evaluator->passes($user, $guide->conditions, $guide->module)) {
                $visible[] = $guide;
            }
        }

        return $visible;
    }
}
