<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

/**
 * The core's own mail categories.
 */
final class CoreMailCategories
{
    /** Verification, password reset, email change, welcome and inactive roles: always sent. */
    public const string ACCOUNT = 'core.account';

    /** Inactive roles of someone the person can grant roles to. */
    public const string ROLE_MANAGEMENT = 'core.role-management';

    public static function register(MailCategoryRegistryInterface $registry): void
    {
        $registry->registerMany([
            MailCategoryDTO::mandatory(
                key: self::ACCOUNT,
                section: 'mail.preferences.sections.account',
                label: 'mail.preferences.categories.account.label',
                description: 'mail.preferences.categories.account.description',
                reason: 'mail.preferences.categories.account.reason',
                icon: 'circle-user-round',
            ),
            MailCategoryDTO::optional(
                key: self::ROLE_MANAGEMENT,
                section: 'mail.preferences.sections.site_management',
                label: 'mail.preferences.categories.role_management.label',
                description: 'mail.preferences.categories.role_management.description',
                // The same permission that picks who gets InactiveRolesManagerNotification
                audience: static fn (object $user): bool => app(AuthorizationServiceInterface::class)
                    ->can($user, RoleGrantPolicy::ASSIGN_ROLES_PERMISSION),
                icon: 'shield-check',
            ),
        ]);

        // Framework notifications, which cannot implement HasMailCategory
        $registry->assignNotification(VerifyEmail::class, self::ACCOUNT);
        $registry->assignNotification(ResetPassword::class, self::ACCOUNT);
    }
}
