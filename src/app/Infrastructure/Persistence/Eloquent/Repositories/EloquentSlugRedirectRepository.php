<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\SlugRedirect;
use App\Domain\Repositories\SlugRedirectRepositoryInterface;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\SlugRedirectModel;
use DateTimeImmutable;

final readonly class EloquentSlugRedirectRepository implements SlugRedirectRepositoryInterface
{
    public function findByOldSlugAndType(Slug $oldSlug, string $entityType): ?SlugRedirect
    {
        $model = SlugRedirectModel::query()
            ->where('old_slug', $oldSlug->value)
            ->where('entity_type', $entityType)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function save(SlugRedirect $redirect): void
    {
        SlugRedirectModel::query()->updateOrCreate(
            ['id' => $redirect->id()],
            $this->toArray($redirect),
        );
    }

    public function updateAllPointingTo(Slug $oldTarget, Slug $newTarget, string $entityType): void
    {
        SlugRedirectModel::query()
            ->where('new_slug', $oldTarget->value)
            ->where('entity_type', $entityType)
            ->update(['new_slug' => $newTarget->value]);
    }

    public function deleteByEntityId(string $entityId, string $entityType): void
    {
        SlugRedirectModel::query()
            ->where('entity_id', $entityId)
            ->where('entity_type', $entityType)
            ->delete();
    }

    private function toDomain(SlugRedirectModel $model): SlugRedirect
    {
        return new SlugRedirect(
            id: $model->id,
            oldSlug: new Slug($model->old_slug),
            newSlug: new Slug($model->new_slug),
            entityType: $model->entity_type,
            entityId: $model->entity_id,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : new DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(SlugRedirect $redirect): array
    {
        return [
            'id' => $redirect->id(),
            'old_slug' => $redirect->oldSlug()->value,
            'new_slug' => $redirect->newSlug()->value,
            'entity_type' => $redirect->entityType(),
            'entity_id' => $redirect->entityId(),
        ];
    }
}
