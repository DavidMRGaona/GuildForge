<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Sources;

use App\Domain\Media\ValueObjects\MediaFileId;
use Illuminate\Database\Query\Grammars\Grammar;

/**
 * SQL prefilter for the records that may store a file, as wide as the resolver: stored forms may be paths,
 * delivery URLs without extension, URL-encoded or with backslashes, so only the longest run of characters
 * that survive all of those is matched. Callers confirm the exact id in PHP. Public so modules can use it.
 */
final class StoredMediaIdPrefilter
{
    /**
     * A `LIKE` clause over the column and its bindings, or null when the id has no plain run to look for
     * (then every row must be read).
     *
     * @param  string  $column  Taken from the source's own code, never from input
     * @return array{0: string, 1: list<string>}|null
     */
    public static function likeClause(Grammar $grammar, string $column, MediaFileId $file): ?array
    {
        $piece = self::longestPlainRun($file->value);

        if ($piece === '') {
            return null;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $piece);

        return [$grammar->wrap($column)." LIKE ? ESCAPE '\\'", ['%'.$escaped.'%']];
    }

    /**
     * Longest run of letters, digits, hyphens and underscores: never encoded, never case-folded by the resolver.
     */
    public static function longestPlainRun(string $id): string
    {
        $runs = preg_split('/[^A-Za-z0-9_-]+/', $id, -1, PREG_SPLIT_NO_EMPTY);
        $best = '';

        foreach ($runs === false ? [] : $runs as $run) {
            if (strlen($run) > strlen($best)) {
                $best = $run;
            }
        }

        return $best;
    }
}
