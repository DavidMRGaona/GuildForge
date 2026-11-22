<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Sources;

use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\Services\UserProfileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The banners of public profiles (core#743), one per profile, stored by its owner under
 * `profiles/banners/<Y>/<m>/`. Listed in the library like any other image, titled by the public name
 * (never the account name) or the address, and without a link: only the owner edits it, from the profile.
 *
 * The library shows a banner only when every signed-in member may see its profile (core#837): a public name,
 * an account neither deactivated nor anonymized, and a `public` or `members` visibility. Any other banner is
 * a hidden usage, so the panel team never sees one the profile page would not show them.
 */
final class ProfileBannerMediaSource extends EloquentColumnMediaSource implements ShowsMediaPreviews
{
    public const string TYPE = 'core.profile-banner';

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('filament.media_library.sources.profile_banners');
    }

    public function folders(): array
    {
        return [UserProfileService::BANNER_FOLDER];
    }

    public function excludedSubfolders(): array
    {
        return [];
    }

    /**
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array
    {
        // The key is an account id: PostgreSQL rejects a malformed uuid instead of finding nothing
        return Str::isUuid($recordId) ? parent::usagesFor($recordId) : [];
    }

    public function editUrl(string $recordId, object $user): ?string
    {
        return null;
    }

    protected function modelClass(): string
    {
        return UserProfileModel::class;
    }

    protected function column(): string
    {
        return 'banner_public_id';
    }

    protected function role(): string
    {
        return __('filament.media_library.roles.banner');
    }

    /**
     * The public names for the titles and what decides whether the banner is shown, in the same query;
     * usesFile() reads only the banners.
     *
     * @return Builder<Model>
     */
    protected function usagesQuery(): Builder
    {
        return parent::usagesQuery()->with('user:id,display_name,deleted_at,anonymized_at');
    }

    protected function titleOf(Model $record): string
    {
        $user = $record->getRelation('user');
        $publicName = $user instanceof UserModel ? trim((string) $user->display_name) : '';

        return $publicName !== '' ? $publicName : '/miembros/'.$record->getAttribute('handle');
    }

    protected function isHidden(Model $record): bool
    {
        $user = $record->getRelation('user');

        if (! $user instanceof UserModel || $user->trashed() || $user->anonymized_at !== null || trim((string) $user->display_name) === '') {
            return true;
        }

        $visibility = $record->getAttribute('visibility');

        return ! $visibility instanceof ProfileVisibility || ! $visibility->isSeenByEveryMember();
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        return null;
    }
}
