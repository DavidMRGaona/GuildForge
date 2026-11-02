<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Infrastructure\Knowledge\Text\TextNormalizer;

/**
 * Cuts a window of plain text around the first match and splits it into segments.
 * Segments are plain text: callers render them escaped, never as HTML.
 */
final class SnippetBuilder
{
    private const string ELLIPSIS = '…';

    /**
     * @param  list<string>  $normalizedTerms
     * @return list<array{text: string, match: bool}>
     */
    public function build(string $text, array $normalizedTerms, int $radius = 60): array
    {
        if ($text === '') {
            return [];
        }

        $characters = mb_str_split($text);
        $length = count($characters);
        $matches = $this->matches(TextNormalizer::normalize($text), $normalizedTerms);
        $radius = max(1, $radius);

        if ($matches === []) {
            [$start, $end] = [0, min($length, 2 * $radius)];
            $end = $this->snapEnd($characters, $end, 0, $length);
        } else {
            [$first, $firstEnd] = $matches[0];
            $start = $this->snapStart($characters, max(0, $first - $radius), $first);
            $end = $this->snapEnd($characters, min($length, $firstEnd + $radius), $firstEnd, $length);
        }

        $segments = [];
        if ($start > 0) {
            $segments[] = ['text' => self::ELLIPSIS, 'match' => false];
        }

        $cursor = $start;
        foreach ($matches as [$matchStart, $matchEnd]) {
            $matchStart = max($matchStart, $start);
            $matchEnd = min($matchEnd, $end);
            if ($matchStart >= $matchEnd) {
                continue;
            }
            $segments[] = ['text' => $this->slice($characters, $cursor, $matchStart), 'match' => false];
            $segments[] = ['text' => $this->slice($characters, $matchStart, $matchEnd), 'match' => true];
            $cursor = $matchEnd;
        }
        $segments[] = ['text' => $this->slice($characters, $cursor, $end), 'match' => false];

        if ($end < $length) {
            $segments[] = ['text' => self::ELLIPSIS, 'match' => false];
        }

        return self::compact($segments);
    }

    /**
     * Joins neighbouring segments of the same kind, collapses whitespace and drops empty ones.
     *
     * @param  list<array{text: string, match: bool}>  $segments
     * @return list<array{text: string, match: bool}>
     */
    public static function compact(array $segments): array
    {
        $compacted = [];
        foreach ($segments as $segment) {
            $text = preg_replace('/\s+/u', ' ', $segment['text']) ?? $segment['text'];
            if ($text === '') {
                continue;
            }

            $last = array_key_last($compacted);
            if ($last !== null && $compacted[$last]['match'] === $segment['match']) {
                $compacted[$last]['text'] = preg_replace('/\s+/u', ' ', $compacted[$last]['text'].$text) ?? $compacted[$last]['text'].$text;

                continue;
            }

            $compacted[] = ['text' => $text, 'match' => $segment['match']];
        }

        if ($compacted !== [] && ! $compacted[0]['match']) {
            $compacted[0]['text'] = ltrim($compacted[0]['text']);
        }
        $last = array_key_last($compacted);
        if ($last !== null && ! $compacted[$last]['match']) {
            $compacted[$last]['text'] = rtrim($compacted[$last]['text']);
        }

        return array_values(array_filter($compacted, static fn (array $segment): bool => $segment['text'] !== ''));
    }

    /**
     * Merged match ranges [start, end) in characters, in text order.
     *
     * @param  list<string>  $terms
     * @return list<array{int, int}>
     */
    private function matches(string $normalized, array $terms): array
    {
        $ranges = [];
        foreach ($terms as $term) {
            $term = TextNormalizer::normalize($term);
            $termLength = mb_strlen($term);
            if ($termLength === 0) {
                continue;
            }

            $offset = 0;
            while (($position = mb_strpos($normalized, $term, $offset)) !== false) {
                $ranges[] = [$position, $position + $termLength];
                $offset = $position + $termLength;
            }
        }

        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $merged = [];
        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);
            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);

                continue;
            }
            $merged[] = [$start, $end];
        }

        return $merged;
    }

    /**
     * Moves a cut start forward to the next word, without passing the first match.
     *
     * @param  list<string>  $characters
     */
    private function snapStart(array $characters, int $start, int $limit): int
    {
        if ($start === 0) {
            return 0;
        }

        for ($i = $start; $i < $limit; $i++) {
            if ($this->isSpace($characters[$i])) {
                return $i + 1;
            }
        }

        return $start;
    }

    /**
     * Moves a cut end back to the end of the previous word, without cutting into the first match.
     *
     * @param  list<string>  $characters
     */
    private function snapEnd(array $characters, int $end, int $limit, int $length): int
    {
        if ($end >= $length) {
            return $length;
        }

        for ($i = $end; $i > $limit; $i--) {
            if ($this->isSpace($characters[$i])) {
                return $i;
            }
        }

        return $end;
    }

    private function isSpace(string $character): bool
    {
        return preg_match('/^\s$/u', $character) === 1;
    }

    /**
     * @param  list<string>  $characters
     */
    private function slice(array $characters, int $from, int $to): string
    {
        return implode('', array_slice($characters, $from, max(0, $to - $from)));
    }
}
