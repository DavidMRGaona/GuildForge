<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class EventMediaSource extends EloquentColumnMediaSource implements ReleasesMediaThroughCoreInterface, ShowsMediaPreviews
{
    public function type(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return __('filament.media_library.sources.events');
    }

    public function folders(): array
    {
        return ['events/'];
    }

    protected function modelClass(): string
    {
        return EventModel::class;
    }

    protected function column(): string
    {
        return 'image_public_id';
    }

    protected function role(): string
    {
        return __('filament.media_library.roles.image');
    }

    /**
     * A proposal draft never submitted is its author's alone (HidesUnsubmittedProposals): its image stays out
     * of sight and its title is not given, though the file still counts as used (core#837).
     */
    protected function isHidden(Model $record): bool
    {
        return $record instanceof EventModel && $record->isUnsubmittedProposal();
    }

    protected function titleOf(Model $record): string
    {
        return $this->isHidden($record)
            ? (string) __('filament.media_library.unsubmitted_proposal')
            : (string) $record->getAttribute('title');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        // The panel never opens an unsubmitted draft
        if ($this->isHidden($record) || ! Gate::forUser($user)->allows('update', $record)) {
            return null;
        }

        return EventResource::getUrl('edit', ['record' => $record]);
    }
}
