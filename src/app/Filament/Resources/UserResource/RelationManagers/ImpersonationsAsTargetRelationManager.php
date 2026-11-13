<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Concerns\ListsImpersonationEntries;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

final class ImpersonationsAsTargetRelationManager extends RelationManager
{
    use ListsImpersonationEntries;

    protected static string $relationship = 'impersonationsAsTarget';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.impersonation.history.as_target');
    }

    protected function showsTarget(): bool
    {
        return false;
    }
}
