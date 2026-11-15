<?php

declare(strict_types=1);

namespace App\Application\Profiles\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Profiles\ProfileVisibility;
use Illuminate\Contracts\Auth\Authenticatable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The one place that decides who sees a public profile (core#741). The page, the card and anything else
 * that shows a profile ask it, and a section or counter is only resolved once it said yes.
 *
 * - A deactivated or anonymized account: nobody, its owner included.
 * - The owner: always (the page tells them when nobody else does).
 * - An owner without a public name: nobody else, so the account name never stands in for it.
 * - `public`: anybody. `members`: a signed-in account that need not verify its email first.
 *   `association`: the same, and an association member. `hidden`: nobody else.
 *
 * The viewer is the request's user, so during an impersonation it is decided for the account being
 * impersonated. Fails closed: a check that throws hides the profile and is logged.
 *
 * A class of its own rather than a method of a core interface, like ImpersonationEligibilityRule:
 * modules implement those interfaces in their tests, and a new method would break them.
 */
final readonly class ProfileAccessRule
{
    public function __construct(
        private EmailVerificationPolicyInterface $emails,
        private AssociationMembershipServiceInterface $membership,
        private LoggerInterface $log,
    ) {}

    public function canView(PublicProfileDTO $profile, ?Authenticatable $viewer): bool
    {
        if ($profile->deactivated || $profile->anonymized) {
            return false;
        }

        if ($this->isOwner($profile, $viewer)) {
            return true;
        }

        if (! $profile->hasPublicName()) {
            return false;
        }

        try {
            return match ($profile->visibility) {
                ProfileVisibility::Public => true,
                ProfileVisibility::Members => $this->isMember($viewer),
                // Stricter than `members`, never looser: a member who must still verify sees neither
                ProfileVisibility::Association => $this->isMember($viewer) && $this->membership->isAssociationMember($viewer),
                ProfileVisibility::Hidden => false,
            };
        } catch (Throwable $exception) {
            $this->log->warning('Public profile access check failed; hidden.', [
                'user_id' => $profile->userId,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    public function isOwner(PublicProfileDTO $profile, ?Authenticatable $viewer): bool
    {
        return $viewer instanceof Authenticatable && (string) $viewer->getAuthIdentifier() === $profile->userId;
    }

    /**
     * @phpstan-assert-if-true Authenticatable $viewer
     */
    private function isMember(?Authenticatable $viewer): bool
    {
        return $viewer instanceof Authenticatable && ! $this->emails->mustVerify($viewer);
    }
}
