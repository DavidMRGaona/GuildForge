<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Sources;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Profile pictures: tracked so they count as used, but never listed in the library nor linked.
 */
final class AvatarMediaSource extends EloquentColumnMediaSource
{
    public function type(): string
    {
        return 'avatars';
    }

    public function label(): string
    {
        return __('filament.media_library.sources.avatars');
    }

    public function folders(): array
    {
        return ['avatars/', 'users/'];
    }

    public function excludedSubfolders(): array
    {
        return [];
    }

    public function hidden(): bool
    {
        return true;
    }

    protected function modelClass(): string
    {
        return UserModel::class;
    }

    protected function column(): string
    {
        return 'avatar_public_id';
    }

    protected function role(): string
    {
        return __('filament.media_library.roles.avatar');
    }

    protected function titleOf(Model $record): string
    {
        return (string) $record->getAttribute('name');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        return null;
    }
}
