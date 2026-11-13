<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

/**
 * The core's own mail categories.
 */
final class CoreMailCategories
{
    /**
     * Verification, password reset, email change, welcome and inactive roles: always sent.
     * Only the inactive roles notice also goes to the bell (core#583).
     */
    public const string ACCOUNT = 'core.account';

    /** Inactive roles of someone the person can grant roles to. */
    public const string ROLE_MANAGEMENT = 'core.role-management';

    /** Proposals from the web waiting for review, to whoever holds the type's review permission (core#577). */
    public const string PROPOSALS_REVIEW = 'core.proposals-review';

    /** What happened to the person's own proposals: approved, returned or rejected (core#577). */
    public const string PROPOSALS_STATUS = 'core.proposals-status';

    /** The first publication of an event, an article or a gallery, in the bell only (core#613). */
    public const string SITE_NEWS = 'core.site-news';

    private static function mayHaveProposals(object $user): bool
    {
        if (! $user instanceof UserModel) {
            return false;
        }

        $id = (string) $user->getKey();
        $eligibility = app(ProposalEligibilityServiceInterface::class);

        return array_any(ProposalType::cases(), static fn (ProposalType $type): bool => $eligibility->check($type, $id)->allowed)
            || EventModel::query()->includingUnsubmittedProposals()->where('proposed_by', $id)->exists()
            || ArticleModel::query()->includingUnsubmittedProposals()->where('proposed_by', $id)->exists();
    }

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
                site: true,
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
                // Notices about other people, which come in bursts when the association role changes
                digestible: true,
                site: true,
            ),
            MailCategoryDTO::optional(
                key: self::PROPOSALS_REVIEW,
                section: 'mail.preferences.sections.proposals',
                label: 'mail.preferences.categories.proposals_review.label',
                description: 'mail.preferences.categories.proposals_review.description',
                // The same permissions that pick who gets ProposalToReviewNotification
                audience: static fn (object $user): bool => array_any(
                    ProposalType::cases(),
                    static fn (ProposalType $type): bool => app(AuthorizationServiceInterface::class)
                        ->can($user, $type->reviewPermission()),
                ),
                icon: 'clipboard-check',
                // Proposals arrive in bursts
                digestible: true,
                site: true,
            ),
            MailCategoryDTO::optional(
                key: self::PROPOSALS_STATUS,
                section: 'mail.preferences.sections.proposals',
                label: 'mail.preferences.categories.proposals_status.label',
                description: 'mail.preferences.categories.proposals_status.description',
                // Whoever may propose now, or still has proposals (access may have been lost since)
                audience: static fn (object $user): bool => self::mayHaveProposals($user),
                icon: 'clipboard-check',
                site: true,
            ),
            MailCategoryDTO::optional(
                key: self::SITE_NEWS,
                section: 'mail.preferences.sections.news',
                label: 'mail.preferences.categories.site_news.label',
                description: 'mail.preferences.categories.site_news.description',
                icon: 'newspaper',
                // Every account, grouped in the bell while unread; never mailed
                site: true,
                mail: false,
            ),
        ]);

        // Framework notifications, which cannot implement HasMailCategory
        $registry->assignNotification(VerifyEmail::class, self::ACCOUNT);
        $registry->assignNotification(ResetPassword::class, self::ACCOUNT);
    }
}
