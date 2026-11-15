<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Sources;

use App\Application\Media\DTOs\MediaUsageDTO;
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
 */
final class ProfileBannerMediaSource extends EloquentColumnMediaSource
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
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        return parent::query()->with('user:id,display_name');
    }

    protected function titleOf(Model $record): string
    {
        $user = $record->getRelation('user');
        $publicName = $user instanceof UserModel ? trim((string) $user->display_name) : '';

        return $publicName !== '' ? $publicName : '/miembros/'.$record->getAttribute('handle');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        return null;
    }
}
