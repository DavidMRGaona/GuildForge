<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Search\Search\MeilisearchFilter;
use Meilisearch\Endpoints\Indexes;

/**
 * Meilisearch only ranks: the filter limits it to the candidates, and any other hit is discarded anyway.
 * Candidates are expected to share one locale and audience (those of the first candidate are used).
 */
final readonly class MeilisearchKnowledgeSearch implements KnowledgeSearchInterface
{
    private const int MAX_QUERY_LENGTH = 500;

    public const int CROP_LENGTH = 24;

    /** Fields the help search reads back from the guides index. */
    public const array RETRIEVE_ATTRIBUTES = ['guide_id', 'locale'];

    public const array HIGHLIGHT_ATTRIBUTES = ['body_text', 'summary'];

    public const array CROP_ATTRIBUTES = ['body_text'];

    public function __construct(
        private MeilisearchHitMapper $mapper,
    ) {}

    public function search(string $query, array $candidates, int $limit): array
    {
        $query = trim(mb_substr($query, 0, self::MAX_QUERY_LENGTH));
        if ($query === '' || $candidates === [] || $limit <= 0) {
            return [];
        }

        $options = [
            'filter' => $this->filterFor($candidates),
            'limit' => $limit,
            'attributesToRetrieve' => self::RETRIEVE_ATTRIBUTES,
            'attributesToHighlight' => self::HIGHLIGHT_ATTRIBUTES,
            'attributesToCrop' => self::CROP_ATTRIBUTES,
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

    /**
     * The filter that limits a search of the guides index to the candidates; the site search uses it too.
     *
     * @param  non-empty-list<Guide>  $candidates
     * @return non-empty-string
     */
    public function filterFor(array $candidates): string
    {
        $first = $candidates[0];

        return sprintf(
            'active = true AND locale = %s AND audience = %s AND %s',
            MeilisearchFilter::quote($first->locale),
            MeilisearchFilter::quote($first->audience->value),
            MeilisearchFilter::in('guide_id', array_map(static fn (Guide $guide): string => $guide->id->value(), $candidates)),
        );
    }
}
