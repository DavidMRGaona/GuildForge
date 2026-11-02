<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

/**
 * Expands synonym groups into the two-way map Meilisearch expects.
 */
final class KnowledgeSynonyms
{
    /**
     * @return array<string, list<string>>
     */
    public static function toIndexSettings(mixed $groups): array
    {
        $synonyms = [];
        foreach (is_array($groups) ? $groups : [] as $group) {
            if (! is_array($group)) {
                continue;
            }

            $words = array_values(array_unique(array_filter($group, static fn (mixed $word): bool => is_string($word) && trim($word) !== '')));
            foreach ($words as $word) {
                $others = array_values(array_filter($words, static fn (string $other): bool => $other !== $word));
                if ($others !== []) {
                    $synonyms[$word] = array_values(array_unique([...$synonyms[$word] ?? [], ...$others]));
                }
            }
        }

        return $synonyms;
    }
}
