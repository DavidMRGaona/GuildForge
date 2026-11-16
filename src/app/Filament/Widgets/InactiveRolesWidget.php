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
 * Accounts holding roles that are inactive for them: roles that need association membership
 * while they are not members, and roles that need two-factor authentication while they have it
 * off (core#772). Each role says why. Shown only to someone who could grant at least one of
 * those roles; the paused roles of people they could not grant stay out.
 */
class InactiveRolesWidget extends TableWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $actor = Auth::user();

        if ($actor === null || ! UserResource::canViewAny()) {
            return false;
        }

        $holders = app(InactiveRoleHolders::class);
        $associationRole = app(AssociationMembershipServiceInterface::class)->roleName();

        // Cheap check first: nobody holds an inactive role, nothing to show
        if (! $holders->query($associationRole)->exists() && $holders->withoutTwoFactorIds() === []) {
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
        $grantable = $actor === null || ! UserResource::canViewAny() ? [] : self::grantableInactiveRoleNames($actor);
        // Read once per render, on first use: every row asks
        $withoutTwoFactor = null;
        $lacksTwoFactor = static function (UserModel $record) use (&$withoutTwoFactor): bool {
            $withoutTwoFactor ??= array_flip(array_map(strtolower(...), app(InactiveRoleHolders::class)->withoutTwoFactorIds()));

            return isset($withoutTwoFactor[strtolower((string) $record->getKey())]);
        };

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
                    ->state(static function (UserModel $record) use ($grantable, $associationRole, $lacksTwoFactor): array {
                        $isMember = $associationRole !== null && $record->roles->contains('name', $associationRole);
                        $lacks = $lacksTwoFactor($record);
                        $states = [];

                        foreach ($record->roles as $role) {
                            if (! in_array($role->name, $grantable, true)) {
                                continue;
                            }

                            $reason = match (true) {
                                $role->requires_association_member && ! $isMember => 'membership',
                                $role->requires_two_factor && $lacks => 'two_factor',
                                default => null,
                            };

                            if ($reason !== null) {
                                $states[] = (string) __('filament.users.inactiveWidget.reasons.'.$reason, ['role' => $role->display_name]);
                            }
                        }

                        return $states;
                    })
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false);
    }

    /**
     * Names of the roles that need membership or two-factor authentication and the actor could grant.
     *
     * @return list<string>
     */
    private static function grantableInactiveRoleNames(object $actor): array
    {
        $policy = app(RoleGrantPolicy::class);

        /** @var list<string> $names */
        $names = RoleModel::query()
            ->where(static fn (Builder $roles): Builder => $roles
                ->where('requires_association_member', true)
                ->orWhere('requires_two_factor', true))
            ->pluck('name')
            ->filter(static fn (string $name): bool => $policy->canGrant($actor, $name))
            ->values()
            ->all();

        return $names;
    }

    /**
     * Holders of a grantable role that is inactive for them, for either reason.
     *
     * @param  list<string>  $grantable
     * @return Builder<UserModel>
     */
    private static function holdersQuery(?string $associationRole, array $grantable): Builder
    {
        $holders = app(InactiveRoleHolders::class);
        $notMembers = $holders->query($associationRole)
            ->whereHas('roles', static function (Builder $roles) use ($grantable): void {
                $roles->where('requires_association_member', true)->whereIn('name', $grantable);
            })
            ->select('users.id');

        return UserModel::query()
            ->whereNull('anonymized_at')
            ->where(static fn (Builder $query): Builder => $query
                ->whereIn('users.id', $notMembers)
                ->orWhereIn('users.id', $holders->withoutTwoFactorIds($grantable)));
    }
}
