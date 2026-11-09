<?php

declare(strict_types=1);

use App\Application\Authorization\Services\AdminOnlyPermissionRule;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;

/**
 * Installing modules and applying updates belong to the administrator role alone. Any other role
 * given them before that rule existed loses them here, through the role service, so the role
 * history shows what each role lost. The permission rows themselves are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = RoleModel::query()
            ->where('name', '!=', UserRole::Admin->value)
            ->whereHas('permissions', static fn (Builder $query): Builder => $query->whereIn('key', AdminOnlyPermissionRule::CORE_KEYS))
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $service = app(RoleServiceInterface::class);

        foreach ($roles as $role) {
            $kept = $role->permissions
                ->pluck('key')
                ->map(strval(...))
                ->reject(static fn (string $key): bool => in_array($key, AdminOnlyPermissionRule::CORE_KEYS, true))
                ->values()
                ->all();

            $service->syncPermissions((string) $role->id, $kept);
        }
    }

    public function down(): void
    {
        // Nothing to undo: those permissions never go back to a role other than admin
    }
};
