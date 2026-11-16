<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whether an account is kept out of the panel only because roles that require two-factor
 * authentication are paused (core#772): with it on, those roles would open the panel. The
 * panel's login and the panel itself tell such a person why, and maintenance mode lets them
 * reach «Seguridad» to turn it on. Any failure answers false: the generic refusal stands.
 */
final readonly class TwoFactorPanelAccess
{
    public const string PANEL_PERMISSION = 'admin.access';

    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private ActiveRoleResolver $roles,
    ) {}

    public function pausedOnlyByTwoFactor(object $user): bool
    {
        if (! $user instanceof UserModel || $user->anonymized_at !== null) {
            return false;
        }

        try {
            if ($this->authorization->can($user, self::PANEL_PERMISSION)) {
                return false;
            }

            $withTwoFactor = $this->roles->activeRoles($user, assumeTwoFactor: true);

            if (! $withTwoFactor->contains(static fn (RoleModel $role): bool => $role->requires_two_factor === true)) {
                return false;
            }

            return $withTwoFactor->contains('name', UserRole::Admin->value)
                || in_array(self::PANEL_PERMISSION, $this->roles->permissionKeys($withTwoFactor), true);
        } catch (Throwable $exception) {
            Log::warning('Could not tell whether panel access waits for two-factor authentication.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
