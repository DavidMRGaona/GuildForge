<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Keeps bot accounts (core#863) out of every account query: lists, lookups, sign-in, password
 * resets and sessions. Whoever needs them asks explicitly with withBots().
 *
 * @implements Scope<Model>
 */
final class WithoutBots implements Scope
{
    public const string COLUMN = 'bot_key';

    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->qualifyColumn(self::COLUMN));
    }
}
