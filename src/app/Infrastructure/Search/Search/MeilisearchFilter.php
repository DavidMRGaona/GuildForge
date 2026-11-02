<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

/**
 * Builds pieces of Meilisearch filter expressions with every value quoted, so no value can change the expression.
 */
final class MeilisearchFilter
{
    /**
     * @return non-empty-string
     */
    public static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * @param  list<string>  $values
     * @return non-empty-string
     */
    public static function in(string $field, array $values): string
    {
        return $field.' IN ['.implode(', ', array_map(self::quote(...), $values)).']';
    }
}
