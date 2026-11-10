<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use Illuminate\Database\Eloquent\Model;

final class FakeSoftDeletingMediaSource extends EloquentColumnMediaSource
{
    public function type(): string
    {
        return 'fake.records';
    }

    public function label(): string
    {
        return 'Fakes';
    }

    public function folders(): array
    {
        return ['fake/'];
    }

    protected function modelClass(): string
    {
        return FakeSoftDeletingModel::class;
    }

    protected function column(): string
    {
        return 'image';
    }

    protected function role(): string
    {
        return 'Imagen';
    }

    protected function titleOf(Model $record): string
    {
        return (string) $record->getAttribute('title');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        return null;
    }
}
