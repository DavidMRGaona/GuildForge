<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Infrastructure\Knowledge\Text\TextNormalizer;
use Illuminate\Contracts\Config\Repository;

/**
 * Search in PHP over the candidates, used when no search engine is configured or it fails.
 * Every term must appear in the title, keywords, summary or body (accents and case ignored).
 */
final readonly class DatabaseKnowledgeSearch implements KnowledgeSearchInterface
{
    private const int MAX_QUERY_LENGTH = 500;

    private const int MAX_TERMS = 10;

    private const int MIN_TERM_LENGTH = 2;

    private const int TITLE_WEIGHT = 8;

    private const int KEYWORDS_WEIGHT = 5;

    private const int SUMMARY_WEIGHT = 3;

    private const int BODY_WEIGHT = 1;

    public function __construct(
        private SnippetBuilder $snippets,
        private Repository $config,
    ) {}

    public function search(string $query, array $candidates, int $limit): array
    {
        $terms = $this->terms($query);
        if ($terms === [] || $candidates === [] || $limit <= 0) {
            return [];
        }

        $scored = [];
        foreach ($candidates as $guide) {
            $fields = [
                'title' => TextNormalizer::normalize($guide->title),
                'keywords' => TextNormalizer::normalize(implode(' ', $guide->keywords)),
                'summary' => TextNormalizer::normalize($guide->summary),
                'body' => TextNormalizer::normalize($guide->bodyText),
            ];

            $score = $this->score($fields, $terms);
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
                snippet: $this->snippet($guide, $entry['body'], $terms),
            );
        }

        return $hits;
    }

    /**
     * Normalized query words of at least two characters, at most ten; stop words are dropped while other words remain.
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $normalized = TextNormalizer::normalize(mb_substr($query, 0, self::MAX_QUERY_LENGTH));

        $terms = [];
        foreach (preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $word = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $word) ?? '';
            if (mb_strlen($word) >= self::MIN_TERM_LENGTH && ! in_array($word, $terms, true)) {
                $terms[] = $word;
            }
        }

        $stopWords = $this->stopWords();
        $meaningful = array_values(array_filter($terms, static fn (string $term): bool => ! isset($stopWords[$term])));

        return array_slice($meaningful !== [] ? $meaningful : $terms, 0, self::MAX_TERMS);
    }

    /**
     * @return array<string, true>
     */
    private function stopWords(): array
    {
        $configured = $this->config->get('knowledge.stop_words', []);

        $stopWords = [];
        foreach (is_array($configured) ? $configured : [] as $word) {
            if (is_string($word)) {
                $stopWords[TextNormalizer::normalize($word)] = true;
            }
        }

        return $stopWords;
    }

    /**
     * Zero when any term is missing.
     *
     * @param  array{title: string, keywords: string, summary: string, body: string}  $fields
     * @param  list<string>  $terms
     */
    private function score(array $fields, array $terms): int
    {
        $total = 0;
        foreach ($terms as $term) {
            $score = (str_contains($fields['title'], $term) ? self::TITLE_WEIGHT : 0)
                + (str_contains($fields['keywords'], $term) ? self::KEYWORDS_WEIGHT : 0)
                + (str_contains($fields['summary'], $term) ? self::SUMMARY_WEIGHT : 0)
                + (str_contains($fields['body'], $term) ? self::BODY_WEIGHT : 0);

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
