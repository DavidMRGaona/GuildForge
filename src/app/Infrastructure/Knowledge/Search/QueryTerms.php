<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Infrastructure\Knowledge\Text\TextNormalizer;
use Illuminate\Contracts\Config\Repository;

/**
 * Splits a typed query into search terms with the caps and stop words (knowledge.stop_words) shared by the
 * database searches; each search chooses how words are normalized and what is trimmed from their edges.
 */
final readonly class QueryTerms
{
    public const int MAX_QUERY_LENGTH = 500;

    public const int MAX_TERMS = 10;

    public const int MIN_TERM_LENGTH = 2;

    public function __construct(
        private Repository $config,
    ) {}

    /**
     * Words of at least two characters, at most ten; stop words are dropped while other words remain.
     *
     * @param  callable(string): string  $normalize  applied to the query before it is split
     * @param  string  $edgePattern  regular expression of what is trimmed from both ends of each word
     * @return list<string>
     */
    public function split(string $query, callable $normalize, string $edgePattern): array
    {
        $normalized = $normalize(mb_substr($query, 0, self::MAX_QUERY_LENGTH));

        $terms = [];
        foreach (preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $word = preg_replace($edgePattern, '', $word) ?? '';
            if (mb_strlen($word) >= self::MIN_TERM_LENGTH && ! in_array($word, $terms, true)) {
                $terms[] = $word;
            }
        }

        $stopWords = $this->stopWords();
        $meaningful = array_values(array_filter(
            $terms,
            static fn (string $term): bool => ! isset($stopWords[TextNormalizer::normalize($term)]),
        ));

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
}
