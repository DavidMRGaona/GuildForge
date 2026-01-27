<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getTitleCaseModelLabel(): string
    {
        return mb_lcfirst(static::getModelLabel());
    }
}
