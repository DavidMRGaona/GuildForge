<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Listeners;

use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Domain\Events\AssociationRoleLost;
use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\InactiveRolesManagerNotification;
use App\Notifications\InactiveRolesNotification;

/**
 * Emails the person whose roles went inactive and every active account that could grant
 * all of those roles (never the person twice, never anonymized accounts).
 */
final readonly class NotifyInactiveRoles
{
    public function __construct(
        private PermissionHolderFinder $holders,
        private RoleGrantPolicy $grantPolicy,
        private SiteBrandingServiceInterface $branding,
    ) {}

    public function handle(AssociationRoleLost $event): void
    {
        $person = UserModel::query()->whereNull('anonymized_at')->find($event->userId);
        if (! $person instanceof UserModel || $event->inactiveRoleNames === []) {
            return;
        }

        $roles = RoleModel::query()
            ->whereIn('name', $event->inactiveRoleNames)
            ->orderBy('name')
            ->get()
            ->map(fn (RoleModel $role): string => $role->display_name ?? $role->name)
            ->implode(', ');
        $personName = $person->display_name ?? $person->name;

        $person->notify(new InactiveRolesNotification($this->branding->siteName(), $roles));

        $this->holders
            ->usersWhoCan(RoleGrantPolicy::ASSIGN_ROLES_PERMISSION)
            ->reject(fn (UserModel $manager): bool => $manager->is($person))
            ->filter(fn (UserModel $manager): bool => array_all(
                $event->inactiveRoleNames,
                fn (string $roleName): bool => $this->grantPolicy->canGrant($manager, $roleName),
            ))
            ->unique('id')
            ->each(function (UserModel $manager) use ($personName, $roles): void {
                $manager->notify(new InactiveRolesManagerNotification($personName, $roles));
            });
    }
}
