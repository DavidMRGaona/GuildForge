<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Domain\Knowledge\Entities\Guide;

/**
 * Turns a raw Meilisearch response into hits, keeping only the candidates.
 */
final class MeilisearchHitMapper
{
    public const string HIGHLIGHT_PRE_TAG = "\u{E000}";

    public const string HIGHLIGHT_POST_TAG = "\u{E001}";

    /**
     * @param  array<array-key, mixed>  $raw
     * @param  list<Guide>  $candidates
     * @return list<KnowledgeSearchHitDTO>
     */
    public function map(array $raw, array $candidates, int $limit): array
    {
        $rawHits = $raw['hits'] ?? null;
        if (! is_array($rawHits) || $limit <= 0) {
            return [];
        }

        $byId = [];
        foreach ($candidates as $guide) {
            $byId[$guide->id->value()] = $guide;
        }

        $hits = [];
        foreach ($rawHits as $rawHit) {
            if (! is_array($rawHit)) {
                continue;
            }

            $id = $rawHit['guide_id'] ?? null;
            if (! is_string($id) || ! isset($byId[$id]) || isset($hits[$id])) {
                continue;
            }

            $guide = $byId[$id];
            if (isset($rawHit['locale']) && $rawHit['locale'] !== $guide->locale) {
                continue;
            }

            $formatted = $rawHit['_formatted'] ?? [];
            $hits[$id] = new KnowledgeSearchHitDTO(
                id: $id,
                title: $guide->title,
                categoryId: $guide->categoryId,
                snippet: $this->snippet(is_array($formatted) ? $formatted : []),
            );

            if (count($hits) >= $limit) {
                break;
            }
        }

        return array_values($hits);
    }

    /**
     * The cropped body, or the summary when only the summary has a highlighted match.
     *
     * @param  array<array-key, mixed>  $formatted
     * @return list<array{text: string, match: bool}>
     */
    private function snippet(array $formatted): array
    {
        $body = is_string($formatted['body_text'] ?? null) ? $formatted['body_text'] : '';
        $summary = is_string($formatted['summary'] ?? null) ? $formatted['summary'] : '';

        if (! str_contains($body, self::HIGHLIGHT_PRE_TAG) && str_contains($summary, self::HIGHLIGHT_PRE_TAG)) {
            return $this->segments($summary);
        }

        return $this->segments($body);
    }

    /**
     * @return list<array{text: string, match: bool}>
     */
    private function segments(string $formatted): array
    {
        $parts = preg_split('/('.self::HIGHLIGHT_PRE_TAG.'|'.self::HIGHLIGHT_POST_TAG.')/u', $formatted, -1, PREG_SPLIT_DELIM_CAPTURE);

        $segments = [];
        $match = false;
        foreach ($parts === false ? [] : $parts as $part) {
            if ($part === self::HIGHLIGHT_PRE_TAG) {
                $match = true;
            } elseif ($part === self::HIGHLIGHT_POST_TAG) {
                $match = false;
            } else {
                $segments[] = ['text' => $part, 'match' => $match];
            }
        }

        return SnippetBuilder::compact($segments);
    }
}
