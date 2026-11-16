<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Listeners;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Events\TwoFactorRolesPaused;
use App\Domain\Events\UserActiveRolesChanged;
use App\Domain\Events\UserRestored;
use App\Domain\TwoFactor\Events\TwoFactorAppChanged;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Domain\TwoFactor\Events\TwoFactorReset;
use App\Infrastructure\Authorization\Services\ActiveRoleResolver;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Two-factor authentication turned on or off changes what roles that require it give (core#772).
 * The events come after the commit; this drops the account's cached authorization first (also
 * for a deactivated account, which may be restored within the cache's lifetime), then tells the
 * modules which roles turned on or off (UserActiveRolesChanged) and, when roles paused, the
 * person (TwoFactorRolesPaused). Runs synchronously: the next check in the same request already
 * sees the new state.
 *
 * Nothing here may stop the other listeners of the same event, the mandatory security mail
 * among them: every failure is logged and swallowed.
 */
final readonly class FollowTwoFactorChanges
{
    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private ActiveRoleResolver $roles,
    ) {}

    public function enabled(TwoFactorEnabled $event): void
    {
        $this->follow($event->userId, function (UserModel $user) use ($event): void {
            if (($names = $this->rolesRequiringIt($user)) !== []) {
                Event::dispatch(new UserActiveRolesChanged($event->userId, $names, []));
            }
        });
    }

    public function disabled(TwoFactorDisabled $event): void
    {
        $this->follow($event->userId, $this->paused(...));
    }

    public function reset(TwoFactorReset $event): void
    {
        $this->follow($event->userId, $this->paused(...));
    }

    /**
     * Another app, same account: no role changes, but nothing cached may outlive the setup.
     */
    public function appChanged(TwoFactorAppChanged $event): void
    {
        $this->follow($event->userId, static function (): void {});
    }

    /**
     * Restoring a deactivated account: what it held may have changed meanwhile (two-factor reset,
     * role requirements), so its cached authorization goes too.
     */
    public function restored(UserRestored $event): void
    {
        $this->follow($event->userId, static function (): void {});
    }

    /**
     * @param  callable(UserModel): void  $then  run only for an account that is not anonymized
     */
    private function follow(string $userId, callable $then): void
    {
        try {
            $user = UserModel::withTrashed()->find($userId);

            if (! $user instanceof UserModel) {
                return;
            }

            $this->authorization->invalidateCacheFor($user);
        } catch (Throwable $exception) {
            $this->logFailure($userId, $exception, 'Could not drop the cached authorization after a two-factor change.');

            return;
        }

        // Anonymized accounts hold no roles: nothing to announce
        if ($user->anonymized_at !== null) {
            return;
        }

        try {
            $then($user);
        } catch (Throwable $exception) {
            $this->logFailure($userId, $exception, 'Could not announce the roles a two-factor change paused or activated.');
        }
    }

    private function paused(UserModel $user): void
    {
        if (($names = $this->rolesRequiringIt($user)) === []) {
            return;
        }

        $userId = (string) $user->getKey();
        Event::dispatch(new UserActiveRolesChanged($userId, [], $names));
        Event::dispatch(new TwoFactorRolesPaused($userId, $names));
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

    private function logFailure(string $userId, Throwable $exception, string $message): void
    {
        Log::error($message, [
            'user_id' => $userId,
            'exception' => $exception::class,
        ]);
    }
}
