<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Knowledge;

use App\Infrastructure\Knowledge\Search\QueryTerms;
use App\Infrastructure\Knowledge\Text\TextNormalizer;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

final class QueryTermsTest extends TestCase
{
    private const string LETTERS_AND_NUMBERS = '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u';

    public function test_words_are_normalized_trimmed_and_deduplicated(): void
    {
        $this->assertSame(
            ['inscripcion', 'campana'],
            $this->terms()->split('¿Inscripción?  inscripcion, CAMPAÑA', TextNormalizer::normalize(...), self::LETTERS_AND_NUMBERS),
        );
    }

    public function test_normalizer_and_edge_pattern_come_from_the_caller(): void
    {
        $this->assertSame(
            ['inscripción', '50%', 'foo_bar'],
            $this->terms()->split('¿Inscripción? 50% foo_bar', mb_strtolower(...), '/^[¿?]+|[¿?]+$/u'),
        );
    }

    public function test_short_words_are_dropped(): void
    {
        $this->assertSame(['rol'], $this->terms()->split('x rol', mb_strtolower(...), self::LETTERS_AND_NUMBERS));
    }

    public function test_stop_words_are_dropped_while_other_words_remain(): void
    {
        $terms = $this->terms(['de', 'la']);

        $this->assertSame(['torneo', 'warhammer'], $terms->split('Torneo de Warhammer', mb_strtolower(...), self::LETTERS_AND_NUMBERS));
        $this->assertSame(['de', 'la'], $terms->split('de la', mb_strtolower(...), self::LETTERS_AND_NUMBERS));
    }

    public function test_stop_words_match_whatever_the_accents(): void
    {
        $this->assertSame(['rol'], $this->terms(['mas'])->split('más rol', mb_strtolower(...), self::LETTERS_AND_NUMBERS));
    }

    public function test_at_most_ten_terms(): void
    {
        $words = array_map(static fn (int $n): string => 'palabra'.$n, range(1, 12));

        $this->assertSame(
            array_slice($words, 0, QueryTerms::MAX_TERMS),
            $this->terms()->split(implode(' ', $words), mb_strtolower(...), self::LETTERS_AND_NUMBERS),
        );
    }

    public function test_the_query_is_cut_before_splitting(): void
    {
        $query = str_repeat('a', QueryTerms::MAX_QUERY_LENGTH - 1).' rol';

        $this->assertSame([str_repeat('a', QueryTerms::MAX_QUERY_LENGTH - 1)], $this->terms()->split($query, mb_strtolower(...), self::LETTERS_AND_NUMBERS));
    }

    /**
     * @param  list<string>  $stopWords
     */
    private function terms(array $stopWords = []): QueryTerms
    {
        return new QueryTerms(new Repository(['knowledge' => ['stop_words' => $stopWords]]));
    }
}
