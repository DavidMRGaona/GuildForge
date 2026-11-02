<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Knowledge\DTOs\Response\KnowledgeCategoryPageResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeCategoryResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeGuideResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeGuideSummaryDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeHomeResponseDTO;
use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Entities\KnowledgeCategory;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Repositories\GuideRepositoryInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

final readonly class KnowledgeQueryService implements KnowledgeQueryServiceInterface
{
    private const int FEATURED_LIMIT = 6;

    public function __construct(
        private GuideVisibilityServiceInterface $visibility,
        private GuideRendererInterface $renderer,
        private KnowledgeSearchInterface $search,
        private GuideRepositoryInterface $guides,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function home(object $user, GuideAudience $audience): KnowledgeHomeResponseDTO
    {
        $categories = array_map(
            fn (array $row): KnowledgeCategoryResponseDTO => $this->categoryDto($row['category'], $row['guideCount']),
            $this->visibility->visibleCategories($user, $audience),
        );

        $visible = $this->visibility->visibleGuides($user, $audience);
        $now = $this->now();

        $starred = array_filter($visible, static fn (Guide $guide): bool => $guide->featured);
        $fresh = array_filter(
            $visible,
            fn (Guide $guide): bool => ! $guide->featured && $this->badge($guide, $now) === 'new',
        );

        $featured = array_map(
            fn (Guide $guide): KnowledgeGuideSummaryDTO => $this->summary($guide, $now),
            array_slice([...$starred, ...$fresh], 0, self::FEATURED_LIMIT),
        );

        return new KnowledgeHomeResponseDTO($categories, $featured);
    }

    public function category(object $user, GuideAudience $audience, string $categoryId): ?KnowledgeCategoryPageResponseDTO
    {
        foreach ($this->visibility->visibleCategories($user, $audience) as $row) {
            if ($row['category']->id !== $categoryId) {
                continue;
            }

            $now = $this->now();
            $guides = [];
            foreach ($this->visibility->visibleGuides($user, $audience) as $guide) {
                if ($guide->categoryId === $categoryId) {
                    $guides[] = $this->summary($guide, $now);
                }
            }

            return new KnowledgeCategoryPageResponseDTO($this->categoryDto($row['category'], $row['guideCount']), $guides);
        }

        return null;
    }

    public function guide(object $user, GuideAudience $audience, GuideId $id): ?KnowledgeGuideResponseDTO
    {
        $guide = $this->visibility->findVisible($user, $id, $audience);
        if (! $guide instanceof Guide) {
            return null;
        }

        $category = null;
        foreach ($this->visibility->visibleCategories($user, $audience) as $row) {
            if ($row['category']->id === $guide->categoryId) {
                $category = $this->categoryDto($row['category'], $row['guideCount']);
                break;
            }
        }
        if (! $category instanceof KnowledgeCategoryResponseDTO) {
            return null;
        }

        $now = $this->now();
        $visible = $this->visibility->visibleGuides($user, $audience);

        $byId = [];
        $siblings = [];
        foreach ($visible as $candidate) {
            $byId[$candidate->id->value()] = $candidate;
            if ($candidate->categoryId === $guide->categoryId) {
                $siblings[] = $this->summary($candidate, $now);
            }
        }

        $related = [];
        foreach ($guide->related as $relatedId) {
            if (isset($byId[$relatedId->value()])) {
                $related[] = $this->summary($byId[$relatedId->value()], $now);
            }
        }

        return new KnowledgeGuideResponseDTO(
            id: $guide->id->value(),
            title: $guide->title,
            summary: $guide->summary,
            category: $category,
            html: $this->renderer->toHtml($guide, $user),
            badge: $this->badge($guide, $now),
            related: $related,
            siblings: $siblings,
        );
    }

    public function search(object $user, GuideAudience $audience, string $query, int $limit): array
    {
        return $this->search->search($query, $this->visibility->visibleGuides($user, $audience), $limit);
    }

    public function resolveAlias(GuideId $id): ?GuideId
    {
        return $this->guides->resolveAlias($id);
    }

    public function visibleIds(object $user, GuideAudience $audience): array
    {
        return array_map(
            static fn (Guide $guide): string => $guide->id->value(),
            $this->visibility->visibleGuides($user, $audience),
        );
    }

    private function categoryDto(KnowledgeCategory $category, int $guideCount): KnowledgeCategoryResponseDTO
    {
        return new KnowledgeCategoryResponseDTO(
            id: $category->id,
            label: (string) __($category->labelKey),
            icon: $category->icon,
            guideCount: $guideCount,
        );
    }

    private function summary(Guide $guide, DateTimeImmutable $now): KnowledgeGuideSummaryDTO
    {
        return new KnowledgeGuideSummaryDTO(
            id: $guide->id->value(),
            title: $guide->title,
            summary: $guide->summary,
            categoryId: $guide->categoryId,
            badge: $this->badge($guide, $now),
        );
    }

    /**
     * @return 'new'|'updated'|null
     */
    private function badge(Guide $guide, DateTimeImmutable $now): ?string
    {
        $limit = $now->modify('-'.max(0, (int) config('knowledge.badge_days')).' days');

        if ($guide->firstSeenAt instanceof DateTimeImmutable && $guide->firstSeenAt >= $limit) {
            return 'new';
        }

        if ($guide->contentChangedAt instanceof DateTimeImmutable && $guide->contentChangedAt >= $limit) {
            return 'updated';
        }

        return null;
    }

    /**
     * TimezoneServiceInterface has no "now": the badge compares two instants, so any zone gives the
     * same answer; the instant is normalised to the storage zone like the stored ones.
     */
    private function now(): DateTimeImmutable
    {
        return $this->timezone->toStorage(CarbonImmutable::now());
    }
}
