<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Filament\Resources\UserResource;
use App\Infrastructure\Authorization\Services\InactiveRoleHolders;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Accounts holding roles that need association membership without being members. Shown only
 * to someone who could grant at least one of those roles, the people the inactive-role email
 * also reaches.
 */
class InactiveRolesWidget extends TableWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $actor = Auth::user();

        if ($actor === null) {
            return false;
        }

        $holders = app(InactiveRoleHolders::class);
        $associationRole = app(AssociationMembershipServiceInterface::class)->roleName();

        // Cheap check first: nobody holds an inactive role, nothing to show
        if (! $holders->query($associationRole)->exists()) {
            return false;
        }

        $grantable = self::grantableInactiveRoleNames($actor);

        return $grantable !== []
            && self::holdersQuery($associationRole, $grantable)->exists();
    }

    public function getTableHeading(): string
    {
        return __('filament.users.inactiveWidget.heading');
    }

    public function table(Table $table): Table
    {
        $limit = app(DashboardWidgetConfigServiceInterface::class)
            ->getLimit(static::class, 5);
        $associationRole = app(AssociationMembershipServiceInterface::class)->roleName();
        $actor = Auth::user();
        $grantable = $actor === null ? [] : self::grantableInactiveRoleNames($actor);

        return $table
            ->query(
                self::holdersQuery($associationRole, $grantable)
                    ->with('roles')
                    ->orderBy('users.name')
                    ->limit($limit)
            )
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('filament.users.inactiveWidget.person'))
                    ->state(static fn (UserModel $record): string => $record->display_name ?? $record->name)
                    ->url(static fn (UserModel $record): ?string => UserResource::canEdit($record)
                        ? UserResource::getUrl('edit', ['record' => $record])
                        : null),
                TextColumn::make('inactive_roles')
                    ->label(__('filament.users.inactiveWidget.roles'))
                    ->state(static fn (UserModel $record): array => $record->roles
                        ->where('requires_association_member', true)
                        ->pluck('display_name')
                        ->values()
                        ->all())
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false);
    }

    /**
     * Names of the roles that need membership and the actor could grant.
     *
     * @return list<string>
     */
    private static function grantableInactiveRoleNames(object $actor): array
    {
        $policy = app(RoleGrantPolicy::class);

        /** @var list<string> $names */
        $names = RoleModel::query()
            ->where('requires_association_member', true)
            ->pluck('name')
            ->filter(static fn (string $name): bool => $policy->canGrant($actor, $name))
            ->values()
            ->all();

        return $names;
    }

    /**
     * @param  list<string>  $grantable
     * @return Builder<UserModel>
     */
    private static function holdersQuery(?string $associationRole, array $grantable): Builder
    {
        return app(InactiveRoleHolders::class)
            ->query($associationRole)
            ->whereHas('roles', static function (Builder $roles) use ($grantable): void {
                $roles->where('requires_association_member', true)->whereIn('name', $grantable);
            });
    }
}
