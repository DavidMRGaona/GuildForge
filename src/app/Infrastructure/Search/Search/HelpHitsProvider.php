<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The help guides as rows of the site search, the only live source: a link to the guide, its category as the
 * meta line, never past. Which guides a user sees is decided by the knowledge services, not here.
 */
final readonly class HelpHitsProvider
{
    public const string TYPE = 'help';

    public function __construct(
        private KnowledgeQueryServiceInterface $knowledge,
        private GuideVisibilityServiceInterface $visibility,
        private EmailVerificationPolicyInterface $emailVerification,
        private LoggerInterface $logger,
    ) {}

    /**
     * Whether the search may offer help: only to a signed-in user who need not verify their email first,
     * as the help centre itself is closed to the others. Fails closed.
     */
    public function availableFor(?object $user): bool
    {
        if ($user === null) {
            return false;
        }

        try {
            return ! $this->emailVerification->mustVerify($user);
        } catch (Throwable $exception) {
            $this->logger->warning('Help in the site search left out: the email verification rule failed.', ['exception' => $exception]);

            return false;
        }
    }

    /**
     * The best matches of the help centre's own search (database or engine), best first.
     *
     * @return list<SiteSearchHitDTO>
     */
    public function hits(object $user, string $query, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return array_values($this->fromKnowledgeHits($this->knowledge->search($user, GuideAudience::Site, $query, $limit), $user));
    }

    /**
     * @param  list<KnowledgeSearchHitDTO>  $hits
     * @return array<string, SiteSearchHitDTO> keyed by guide id, in the order given
     */
    public function fromKnowledgeHits(array $hits, object $user): array
    {
        if ($hits === []) {
            return [];
        }

        $labels = [];
        foreach ($this->visibility->visibleCategories($user, GuideAudience::Site) as $row) {
            $labels[$row['category']->id] = (string) __($row['category']->labelKey);
        }

        $rows = [];
        foreach ($hits as $hit) {
            $rows[$hit->id] = new SiteSearchHitDTO(
                type: self::TYPE,
                title: $hit->title,
                url: route('knowledge.show', $hit->id),
                meta: $labels[$hit->categoryId] ?? null,
                imagePublicId: null,
                startsAt: null,
                endsAt: null,
                past: false,
                snippet: $hit->snippet,
            );
        }

        return $rows;
    }
}
