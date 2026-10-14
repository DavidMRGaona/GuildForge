<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * Reads the setting on every call (SettingsService caches it): as a singleton it must not
 * keep a value across requests or queue jobs.
 */
final readonly class AssociationMembershipService implements AssociationMembershipServiceInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function roleName(): ?string
    {
        $stored = $this->settings->get(self::SETTING_KEY);
        $roleName = is_scalar($stored) ? trim((string) $stored) : '';

        if ($roleName === '') {
            return self::DEFAULT_ROLE;
        }

        if (in_array(mb_strtolower($roleName), self::FORBIDDEN_ROLES, true)) {
            $this->warnForbidden($roleName);

            return null;
        }

        return $roleName;
    }

    public function isAssociationMember(object $user): bool
    {
        $roleName = $this->roleName();

        // A configured role that does not exist is held by nobody: hasRole() is false on its own
        return $roleName !== null && $this->authorization->hasRole($user, $roleName);
    }

    private function warnForbidden(string $roleName): void
    {
        $warning = app(AssociationMemberRoleWarning::class);

        if ($warning->logged) {
            return;
        }

        $warning->logged = true;
        Log::warning("The association member role setting holds the base role \"{$roleName}\"; nobody is treated as an association member until another role is chosen in the site settings");
    }
}
