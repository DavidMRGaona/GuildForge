<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Filament\Resources\ArticleResource;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class ArticleMediaSource extends EloquentColumnMediaSource implements ReleasesMediaThroughCoreInterface
{
    public function type(): string
    {
        return 'articles';
    }

    public function label(): string
    {
        return __('filament.media_library.sources.articles');
    }

    public function folders(): array
    {
        return ['articles/'];
    }

    protected function modelClass(): string
    {
        return ArticleModel::class;
    }

    protected function column(): string
    {
        return 'featured_image_public_id';
    }

    protected function role(): string
    {
        return __('filament.media_library.roles.featured_image');
    }

    protected function titleOf(Model $record): string
    {
        return (string) $record->getAttribute('title');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        if (! Gate::forUser($user)->allows('update', $record)) {
            return null;
        }

        return ArticleResource::getUrl('edit', ['record' => $record]);
    }
}
