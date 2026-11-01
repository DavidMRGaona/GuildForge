<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Meilisearch\Endpoints\Indexes;

/**
 * Meilisearch only ranks: the filter limits it to the candidates, and any other hit is discarded anyway.
 * Candidates are expected to share one locale and audience (those of the first candidate are used).
 */
final readonly class MeilisearchKnowledgeSearch implements KnowledgeSearchInterface
{
    private const int MAX_QUERY_LENGTH = 500;

    private const int CROP_LENGTH = 24;

    public function __construct(
        private MeilisearchHitMapper $mapper,
    ) {}

    public function search(string $query, array $candidates, int $limit): array
    {
        $query = trim(mb_substr($query, 0, self::MAX_QUERY_LENGTH));
        if ($query === '' || $candidates === [] || $limit <= 0) {
            return [];
        }

        $first = $candidates[0];
        $ids = array_map(fn ($guide): string => $this->quote($guide->id->value()), $candidates);
        $options = [
            'filter' => sprintf(
                'active = true AND locale = %s AND audience = %s AND guide_id IN [%s]',
                $this->quote($first->locale),
                $this->quote($first->audience->value),
                implode(', ', $ids),
            ),
            'limit' => $limit,
            'attributesToRetrieve' => ['guide_id', 'locale'],
            'attributesToHighlight' => ['body_text', 'summary'],
            'attributesToCrop' => ['body_text'],
            'cropLength' => self::CROP_LENGTH,
            'highlightPreTag' => MeilisearchHitMapper::HIGHLIGHT_PRE_TAG,
            'highlightPostTag' => MeilisearchHitMapper::HIGHLIGHT_POST_TAG,
        ];

        $raw = KnowledgeGuideModel::search(
            $query,
            static fn (Indexes $index, string $query): array => $index->rawSearch($query, $options),
        )->raw();

        return $this->mapper->map(is_array($raw) ? $raw : [], $candidates, $limit);
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
