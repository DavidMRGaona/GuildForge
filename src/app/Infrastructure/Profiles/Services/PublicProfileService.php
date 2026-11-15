<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\DTOs\ProfileLookupDTO;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\Services\ProfileAccessRule;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Finds a profile by its current address, then by the history (an address retired by its owner), and
 * lets ProfileAccessRule decide before saying anything about it.
 */
final readonly class PublicProfileService implements PublicProfileServiceInterface
{
    public function __construct(
        private ProfileAccessRule $access,
        private AssociationMembershipServiceInterface $membership,
        private LoggerInterface $log,
    ) {}

    public function findByHandle(string $handle, ?Authenticatable $viewer): ProfileLookupDTO
    {
        $requested = ProfileHandle::tryFrom($handle);

        if (! $requested instanceof ProfileHandle) {
            return ProfileLookupDTO::notFound();
        }

        $row = $this->findRow($requested);
        $owner = $row instanceof UserProfileModel ? UserModel::withTrashed()->find($row->user_id) : null;

        if (! $row instanceof UserProfileModel || ! $owner instanceof UserModel) {
            return ProfileLookupDTO::notFound();
        }

        $profile = $this->toDto($row, $owner);

        if (! $this->access->canView($profile, $viewer)) {
            return ProfileLookupDTO::notFound();
        }

        if ($handle !== $row->handle) {
            return ProfileLookupDTO::movedTo($row->handle);
        }

        return ProfileLookupDTO::visible($profile, $this->access->isOwner($profile, $viewer), $this->membershipBadge($row, $owner));
    }

    private function findRow(ProfileHandle $handle): ?UserProfileModel
    {
        $current = UserProfileModel::query()->where('handle', $handle->value)->first();

        if ($current instanceof UserProfileModel) {
            return $current;
        }

        // A retired address still names its owner until the account is forgotten (user_id null then)
        $userId = UserProfileHandleModel::query()
            ->whereKey($handle->hash())
            ->whereNotNull('user_id')
            ->value('user_id');

        return is_string($userId) ? UserProfileModel::query()->find($userId) : null;
    }

    private function toDto(UserProfileModel $row, UserModel $owner): PublicProfileDTO
    {
        return new PublicProfileDTO(
            userId: (string) $owner->id,
            handle: $row->handle,
            publicName: $owner->display_name,
            visibility: $row->visibility,
            avatarPublicId: $owner->avatar_public_id,
            bannerPublicId: $row->banner_public_id,
            showMembership: $row->show_membership,
            memberSince: $owner->created_at?->toDateTimeImmutable(),
            deactivated: $owner->trashed(),
            anonymized: $owner->anonymized_at !== null,
        );
    }

    /**
     * Membership is personal data: shown only when the owner chose to, and left out when it cannot be told.
     */
    private function membershipBadge(UserProfileModel $row, UserModel $owner): bool
    {
        if (! $row->show_membership) {
            return false;
        }

        try {
            return $this->membership->isAssociationMember($owner);
        } catch (Throwable $exception) {
            $this->log->warning('Public profile membership check failed; badge left out.', [
                'user_id' => (string) $owner->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }
}
