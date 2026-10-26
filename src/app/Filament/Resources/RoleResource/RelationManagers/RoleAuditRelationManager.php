<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Filament\Concerns\ListsRoleAuditEntries;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class RoleAuditRelationManager extends RelationManager
{
    use ListsRoleAuditEntries;

    protected static string $relationship = 'auditEntries';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $viewer = Auth::user();

        return $viewer instanceof UserModel && $ownerRecord instanceof RoleModel && $viewer->can('view', $ownerRecord);
    }

    protected function showsAffectedAccount(): bool
    {
        return true;
    }
}
