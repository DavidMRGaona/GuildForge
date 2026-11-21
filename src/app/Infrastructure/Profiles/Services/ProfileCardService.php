<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Profiles\DTOs\ProfileCardDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\Services\ProfileAccessRule;
use App\Application\Profiles\Services\ProfileCardServiceInterface;
use App\Application\Profiles\Services\ProfileSummaryRendererInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\StoredProfiles;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The summary card of a public profile (core#744): ProfileAccessRule first, then the renderer's summary
 * (header and the first stats, no section). The request's profile rows and the renderer are bound
 * scoped, so they are asked for on each call: a module may keep this service longer than one request
 * or job. A class apart from PublicProfileService, so a controller can be given both.
 */
final readonly class ProfileCardService implements ProfileCardServiceInterface
{
    public function __construct(
        private ProfileAccessRule $access,
        private CloudinaryImageUrlBuilder $images,
        private Container $container,
        private LoggerInterface $log,
    ) {}

    public function card(string $userId, ?Authenticatable $viewer): ?ProfileCardDTO
    {
        // Not even asked for otherwise: an id that is not one would abort a PostgreSQL transaction
        if (! Str::isUuid($userId)) {
            return null;
        }

        // Ids are stored in lower case: PostgreSQL's uuid column ignores case and SQLite's text does not,
        // and the rows of the request are kept by id
        $userId = strtolower($userId);

        try {
            $row = $this->container->make(StoredProfiles::class)->find($userId);
            $owner = $row instanceof UserProfileModel ? UserModel::withTrashed()->find($row->user_id) : null;

            if (! $row instanceof UserProfileModel || ! $owner instanceof UserModel) {
                return null;
            }

            $profile = PublicProfileService::describe($row, $owner);

            if (! $this->access->canView($profile, $viewer)) {
                return null;
            }

            // Only now, once the access rule let this person see it
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
                badges: $header->badges,
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
}
