<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\DTOs\ProfileEditorDTO;
use App\Application\Profiles\DTOs\ProfileLayoutOptionDTO;
use App\Application\Profiles\Services\ProfileEditorInterface;
use App\Application\Profiles\Services\ProfileLayoutOptionsInterface;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Profiles\ValueObjects\ProfileLayoutEntry;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The owner's editor (core#743). Reads the profile row through the request's StoredProfiles, so the
 * page, the renderer and the editor read it once.
 */
final readonly class ProfileEditor implements ProfileEditorInterface
{
    public function __construct(
        private StoredProfiles $rows,
        private UserProfileServiceInterface $profiles,
        private ProfileLayoutOptionsInterface $options,
        private AssociationMembershipServiceInterface $membership,
        private TimezoneServiceInterface $timezone,
        private LoggerInterface $log,
    ) {}

    public function ownerId(string $handle, ?Authenticatable $user): ?string
    {
        if (! $user instanceof UserModel || $user->trashed() || $user->anonymized_at !== null) {
            return null;
        }

        try {
            $userId = (string) $user->getKey();
            $profile = $this->rows->find($userId);

            // Only the current address exactly as it is: an old one belongs to the history, not to an edit
            return $profile instanceof UserProfileModel && $profile->handle === $handle ? $userId : null;
        } catch (Throwable $exception) {
            $this->log->warning('[Profiles] The owner of a profile could not be checked; editing is refused.', [
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    public function editorFor(string $userId): ?ProfileEditorDTO
    {
        $profile = $this->rows->find($userId);
        $user = UserModel::query()->find($userId);

        if (! $profile instanceof UserProfileModel || ! $user instanceof UserModel) {
            return null;
        }

        $layout = $profile->layout;

        return new ProfileEditorDTO(
            handle: $profile->handle,
            visibility: $profile->visibility,
            bio: $profile->bio,
            bannerPublicId: $profile->banner_public_id === '' ? null : $profile->banner_public_id,
            showMembership: $profile->show_membership,
            canShowMembership: $this->isAssociationMember($user),
            nextHandleChangeAt: $this->timezone->iso($this->profiles->nextHandleChangeAt($userId)),
            sections: $this->ordered($this->options->sections(), $layout->sections),
            stats: $this->ordered($this->options->stats(), $layout->stats),
        );
    }

    /**
     * Fails closed: a membership check that throws offers no badge.
     */
    private function isAssociationMember(UserModel $user): bool
    {
        try {
            return $this->membership->isAssociationMember($user);
        } catch (Throwable $exception) {
            $this->log->warning('[Profiles] The membership of a profile owner could not be checked; the badge is not offered.', [
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * What is offered in the owner's order, as the page draws it: first what the layout lists (in its
     * order), then the rest by default order, each on or off as the owner chose or by default.
     *
     * @param  list<ProfileLayoutOptionDTO>  $options
     * @param  list<ProfileLayoutEntry>  $entries
     * @return list<array{option: ProfileLayoutOptionDTO, enabled: bool}>
     */
    private function ordered(array $options, array $entries): array
    {
        $byKey = [];
        foreach ($options as $option) {
            $byKey[$option->key] = $option;
        }

        $ordered = [];
        foreach ($entries as $entry) {
            if (isset($byKey[$entry->key]) && ! isset($ordered[$entry->key])) {
                $ordered[$entry->key] = ['option' => $byKey[$entry->key], 'enabled' => $entry->enabled];
            }
        }

        foreach ($byKey as $key => $option) {
            $ordered[$key] ??= ['option' => $option, 'enabled' => $option->enabledByDefault];
        }

        return array_values($ordered);
    }
}
