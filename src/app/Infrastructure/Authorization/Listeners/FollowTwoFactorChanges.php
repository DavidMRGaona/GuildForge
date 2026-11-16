<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Listeners;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Events\TwoFactorRolesPaused;
use App\Domain\Events\UserActiveRolesChanged;
use App\Domain\TwoFactor\Events\TwoFactorAppChanged;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Domain\TwoFactor\Events\TwoFactorReset;
use App\Infrastructure\Authorization\Services\ActiveRoleResolver;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Event;

/**
 * Two-factor authentication turned on or off changes what roles that require it give (core#772).
 * The events come after the commit; this drops the account's cached authorization first, then
 * tells the modules which roles turned on or off (UserActiveRolesChanged) and, when roles
 * paused, the person (TwoFactorRolesPaused). Runs synchronously: the next check in the same
 * request already sees the new state.
 */
final readonly class FollowTwoFactorChanges
{
    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private ActiveRoleResolver $roles,
    ) {}

    public function enabled(TwoFactorEnabled $event): void
    {
        $user = $this->refresh($event->userId);

        if ($user instanceof UserModel && ($names = $this->rolesRequiringIt($user)) !== []) {
            Event::dispatch(new UserActiveRolesChanged($event->userId, $names, []));
        }
    }

    public function disabled(TwoFactorDisabled $event): void
    {
        $this->paused($event->userId);
    }

    public function reset(TwoFactorReset $event): void
    {
        $this->paused($event->userId);
    }

    /**
     * Another app, same account: no role changes, but nothing cached may outlive the setup.
     */
    public function appChanged(TwoFactorAppChanged $event): void
    {
        $this->refresh($event->userId);
    }

    private function paused(string $userId): void
    {
        $user = $this->refresh($userId);

        if (! $user instanceof UserModel || ($names = $this->rolesRequiringIt($user)) === []) {
            return;
        }

        Event::dispatch(new UserActiveRolesChanged($userId, [], $names));
        Event::dispatch(new TwoFactorRolesPaused($userId, $names));
    }

    /**
     * Anonymized accounts hold no roles; deactivated ones are left out like elsewhere.
     */
    private function refresh(string $userId): ?UserModel
    {
        $user = UserModel::query()->whereNull('anonymized_at')->find($userId);

        if ($user instanceof UserModel) {
            $this->authorization->invalidateCacheFor($user);
        }

        return $user;
    }

    /**
     * The roles that require two-factor authentication and count for the account with it on:
     * the ones its state turns on or off (a role also waiting for membership is not among them).
     *
     * @return list<string>
     */
    private function rolesRequiringIt(UserModel $user): array
    {
        /** @var list<string> $names */
        $names = $this->roles->activeRoles($user, assumeTwoFactor: true)
            ->where('requires_two_factor', true)
            ->pluck('name')
            ->map(strval(...))
            ->sort()
            ->values()
            ->all();

        return $names;
    }
}
