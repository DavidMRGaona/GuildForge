<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileCardDTO;
use App\Application\Profiles\DTOs\ProfileLookupDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\Services\ProfileAccessRule;
use App\Application\Profiles\Services\ProfileSummaryRendererInterface;
use App\Application\Profiles\Services\PublicProfileServiceInterface;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\StoredProfiles;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Finds a profile by its current address, then by the history (an address retired by its owner), and
 * lets ProfileAccessRule decide before saying anything about it. What the page draws is the renderer's;
 * the card (core#744) is the renderer's summary, asked for only once the rule let the viewer see it.
 */
final readonly class PublicProfileService implements PublicProfileServiceInterface
{
    public function __construct(
        private ProfileAccessRule $access,
        private CloudinaryImageUrlBuilder $images,
        private Container $container,
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

        return ProfileLookupDTO::visible($profile, $this->access->isOwner($profile, $viewer));
    }

    public function card(string $userId, ?Authenticatable $viewer): ?ProfileCardDTO
    {
        // Not even asked for otherwise: an id that is not one would abort a PostgreSQL transaction
        if (! Str::isUuid($userId)) {
            return null;
        }

        try {
            $row = $this->rows()->find($userId);
            $owner = $row instanceof UserProfileModel ? UserModel::withTrashed()->find($row->user_id) : null;

            if (! $row instanceof UserProfileModel || ! $owner instanceof UserModel) {
                return null;
            }

            $profile = $this->toDto($row, $owner);

            if (! $this->access->canView($profile, $viewer)) {
                return null;
            }

            // Only now, once the access rule let this person see it; asked for on each call, like rows()
            $summary = $this->container->make(ProfileSummaryRendererInterface::class)->summary(
                new ProfileOwnerDTO($profile->userId, $profile->handle, $profile->shownPublicName()),
                $viewer,
            );
            $header = $summary->header;

            return new ProfileCardDTO(
                handle: $header->handle,
                publicName: $header->publicName,
                url: route('profiles.show', ['handle' => $header->handle]),
                avatarUrl: $header->avatarPublicId === null ? null : $this->images->avatar($header->avatarPublicId, ProfileCardDTO::AVATAR_SIZE),
                bannerUrl: $header->bannerPublicId === null ? null : $this->images->banner($header->bannerPublicId, ProfileCardDTO::BANNER_WIDTH, ProfileCardDTO::BANNER_HEIGHT),
                memberSince: $header->memberSince,
                showsMembership: $header->showsMembership,
                isOwner: $header->isOwner,
                stats: array_slice($summary->stats, 0, ResolvedProfileDTO::CARD_STATS),
            );
        } catch (Throwable $exception) {
            // Only the class: a message may carry what a person can see
            $this->log->warning('The card of a public profile could not be made; none is shown.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);

            return null;
        }
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

        return is_string($userId) ? $this->rows()->find($userId) : null;
    }

    /**
     * The request's (or job's) profile rows, asked for on each call: they are bound scoped, and a module
     * may keep this service longer than one request or job.
     */
    private function rows(): StoredProfiles
    {
        return $this->container->make(StoredProfiles::class);
    }

    private function toDto(UserProfileModel $row, UserModel $owner): PublicProfileDTO
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
