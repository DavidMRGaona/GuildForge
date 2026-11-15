<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileLookupDTO;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\Services\ProfileAccessRule;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Finds a profile by its current address, then by the history (an address retired by its owner), and
 * lets ProfileAccessRule decide before saying anything about it. What the page draws is the renderer's.
 */
final readonly class PublicProfileService implements PublicProfileServiceInterface
{
    public function __construct(
        private ProfileAccessRule $access,
        private StoredProfiles $rows,
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

        $profile = self::describe($row, $owner);

        if (! $this->access->canView($profile, $viewer)) {
            return ProfileLookupDTO::notFound();
        }

        if ($handle !== $row->handle) {
            return ProfileLookupDTO::movedTo($row->handle);
        }

        return ProfileLookupDTO::visible($profile, $this->access->isOwner($profile, $viewer));
    }

    /**
     * The current address first, then the history (an address retired by its owner still names them
     * until the account is forgotten). The row is read through StoredProfiles, so the renderer reuses it.
     */
    private function findRow(ProfileHandle $handle): ?UserProfileModel
    {
        $userId = UserProfileModel::query()->where('handle', $handle->value)->value('user_id')
            ?? UserProfileHandleModel::query()
                ->whereKey($handle->hash())
                ->whereNotNull('user_id')
                ->value('user_id');

        return is_string($userId) ? $this->rows->find($userId) : null;
    }

    /**
     * What the access rule needs to know about a profile and its owner; ProfileCardService (core#744)
     * describes it the same way.
     */
    public static function describe(UserProfileModel $row, UserModel $owner): PublicProfileDTO
    {
        return new PublicProfileDTO(
            userId: (string) $owner->id,
            handle: $row->handle,
            publicName: $owner->display_name,
            visibility: $row->visibility,
            deactivated: $owner->trashed(),
            anonymized: $owner->anonymized_at !== null,
        );
    }
}
