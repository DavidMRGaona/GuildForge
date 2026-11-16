<?php

declare(strict_types=1);

namespace Tests\Support\Authorization;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Carbon;
use PragmaRX\Google2FA\Google2FA;

/**
 * Roles that require two-factor authentication (core#772) and accounts that turn it on through
 * the real service, so its events (and what listens to them) run as in production.
 */
trait ManagesTwoFactorRoles
{
    /**
     * @param  array<string>  $permissionKeys
     */
    protected function roleRequiringTwoFactor(string $name, array $permissionKeys = [], bool $requiresMembership = false): RoleModel
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        $role->requires_two_factor = true;
        $role->requires_association_member = $requiresMembership;
        $role->save();

        $this->givePermissions($role, $permissionKeys);

        return $role;
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    protected function givePermissions(RoleModel $role, array $permissionKeys): void
    {
        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    /**
     * Straight to the pivot: no assignment events, no history.
     *
     * @param  array<string>  $roleNames
     */
    protected function accountHolding(array $roleNames): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->whereIn('name', $roleNames)->pluck('id')->all());

        return $user;
    }

    protected function turnOnTwoFactor(UserModel $user): void
    {
        $service = $this->app->make(TwoFactorServiceInterface::class);
        $secret = $service->beginSetup((string) $user->id)->secret;
        $service->confirmSetup(
            (string) $user->id,
            (new Google2FA)->oathTotp($secret, intdiv(Carbon::now()->getTimestamp(), 30)),
        );
    }
}
