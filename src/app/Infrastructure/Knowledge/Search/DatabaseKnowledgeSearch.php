<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Infrastructure\Knowledge\Text\TextNormalizer;
use App\Infrastructure\Search\Support\QueryTerms;
use Illuminate\Contracts\Config\Repository;

/**
 * Search in PHP over the candidates, used when no search engine is configured or it fails.
 * Every term must appear in the title, keywords, summary or body (accents and case ignored),
 * itself or as one of its synonyms (knowledge.synonyms).
 */
final readonly class DatabaseKnowledgeSearch implements KnowledgeSearchInterface
{
    /** Anything that is not a letter or a number around a word. */
    private const string EDGE_PATTERN = '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u';

    private const int TITLE_WEIGHT = 8;

    private const int KEYWORDS_WEIGHT = 5;

    private const int SUMMARY_WEIGHT = 3;

    private const int BODY_WEIGHT = 1;

    public function __construct(
        private SnippetBuilder $snippets,
        private QueryTerms $queryTerms,
        private Repository $config,
    ) {}

    public function search(string $query, array $candidates, int $limit): array
    {
        $terms = $this->terms($query);
        if ($terms === [] || $candidates === [] || $limit <= 0) {
            return [];
        }

        $synonyms = $this->synonyms();
        $alternatives = array_map(
            static fn (string $term): array => array_values(array_unique([$term, ...$synonyms[$term] ?? []])),
            $terms,
        );
        $highlighted = array_values(array_unique(array_merge(...$alternatives)));

        $scored = [];
        foreach ($candidates as $guide) {
            $fields = [
                'title' => TextNormalizer::normalize($guide->title),
                'keywords' => TextNormalizer::normalize(implode(' ', $guide->keywords)),
                'summary' => TextNormalizer::normalize($guide->summary),
                'body' => TextNormalizer::normalize($guide->bodyText),
            ];

            $score = $this->score($fields, $alternatives);
            if ($score > 0) {
                $scored[] = ['guide' => $guide, 'score' => $score, 'title' => $fields['title'], 'body' => $fields['body']];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']
            ?: $a['guide']->order <=> $b['guide']->order
            ?: strcmp($a['title'], $b['title'])
            ?: strcmp($a['guide']->id->value(), $b['guide']->id->value()));

        $hits = [];
        foreach (array_slice($scored, 0, $limit) as $entry) {
            $guide = $entry['guide'];
            $hits[] = new KnowledgeSearchHitDTO(
                id: $guide->id->value(),
                title: $guide->title,
                categoryId: $guide->categoryId,
                snippet: $this->snippet($guide, $entry['body'], $highlighted),
            );
        }

        return $hits;
    }

    /**
     * Normalized query words (accents and case ignored).
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        return $this->queryTerms->split($query, TextNormalizer::normalize(...), self::EDGE_PATTERN);
    }

    /**
     * Normalized word or phrase => the other members of its synonym groups (normalized).
     *
     * @return array<string, list<string>>
     */
    private function synonyms(): array
    {
        $groups = [];
        foreach (KnowledgeSynonyms::toIndexSettings($this->config->get('knowledge.synonyms', [])) as $word => $others) {
            $key = TextNormalizer::normalize($word);
            $normalized = array_map(TextNormalizer::normalize(...), $others);
            $groups[$key] = array_values(array_unique([...$groups[$key] ?? [], ...$normalized]));
        }

        return $groups;
    }

    /**
     * Zero when any term is missing; a term scores as its best-matching alternative (itself or a synonym).
     *
     * @param  array{title: string, keywords: string, summary: string, body: string}  $fields
     * @param  list<list<string>>  $alternatives
     */
    private function score(array $fields, array $alternatives): int
    {
        $total = 0;
        foreach ($alternatives as $words) {
            $score = 0;
            foreach ($words as $word) {
                $score = max($score, (str_contains($fields['title'], $word) ? self::TITLE_WEIGHT : 0)
                    + (str_contains($fields['keywords'], $word) ? self::KEYWORDS_WEIGHT : 0)
                    + (str_contains($fields['summary'], $word) ? self::SUMMARY_WEIGHT : 0)
                    + (str_contains($fields['body'], $word) ? self::BODY_WEIGHT : 0));
            }

            if ($score === 0) {
                return 0;
            }
            $total += $score;
        }

        return $total;
    }

    /**
     * From the body when it contains a term, otherwise from the summary.
     *
     * @param  list<string>  $terms
     * @return list<array{text: string, match: bool}>
     */
    private function snippet(Guide $guide, string $normalizedBody, array $terms): array
    {
        foreach ($terms as $term) {
            if (str_contains($normalizedBody, $term)) {
                return $this->snippets->build($guide->bodyText, $terms);
            }
        }

        return $this->snippets->build($guide->summary, $terms);
    }
}
