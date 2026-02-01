<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Static helper methods for common query operations.
 *
 * This class provides reusable query building patterns used across
 * multiple QueryService implementations.
 */
final class QueryHelpers
{
    /**
     * Apply tag filtering to a query if tag slugs are provided.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     * @param  array<string>|null  $tagSlugs
     * @return Builder<TModel>
     */
    public static function applyTagFilter(Builder $builder, ?array $tagSlugs): Builder
    {
        if ($tagSlugs !== null && count($tagSlugs) > 0) {
            $builder->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs));
        }

        return $builder;
    }

    /**
     * Apply pagination (offset and limit) to a query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     * @return Builder<TModel>
     */
    public static function applyPagination(Builder $builder, int $page, int $perPage): Builder
    {
        $offset = self::calculateOffset($page, $perPage);

        return $builder->offset($offset)->limit($perPage);
    }

    /**
     * Calculate the offset for pagination.
     */
    public static function calculateOffset(int $page, int $perPage): int
    {
        return ($page - 1) * $perPage;
    }
}
