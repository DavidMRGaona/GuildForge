<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\DTOs\Response\KnowledgeCategoryResponseDTO;
use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Application\Search\Contracts\SearchSourceInterface;
use App\Application\Search\DTOs\SearchResultDTO;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Knowledge\Enums\GuideAudience;

/**
 * Offers the site help guides the signed-in user may see to the site search.
 */
final readonly class KnowledgeSearchSource implements SearchSourceInterface
{
    public function __construct(
        private KnowledgeQueryServiceInterface $knowledge,
        private EmailVerificationPolicyInterface $emailVerification,
    ) {}

    public function key(): string
    {
        return 'help';
    }

    public function label(): string
    {
        return (string) __('knowledge.search_block');
    }

    public function search(string $query, ?object $user, int $limit): array
    {
        // The help centre is closed to accounts that must verify their email first: its search block is too
        if ($user === null || $this->emailVerification->mustVerify($user)) {
            return [];
        }

        $hits = $this->knowledge->search($user, GuideAudience::Site, $query, $limit);

        if ($hits === []) {
            return [];
        }

        $labels = [];
        foreach ($this->knowledge->home($user, GuideAudience::Site)->categories as $category) {
            /** @var KnowledgeCategoryResponseDTO $category */
            $labels[$category->id] = $category->label;
        }

        return array_map(
            static fn (KnowledgeSearchHitDTO $hit): SearchResultDTO => new SearchResultDTO(
                title: $hit->title,
                url: route('knowledge.show', $hit->id),
                context: $labels[$hit->categoryId] ?? null,
                snippet: $hit->snippet,
            ),
            $hits,
        );
    }
}
