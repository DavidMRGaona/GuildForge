<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use App\Application\Services\SlugRedirectServiceInterface;
use App\Domain\ValueObjects\Slug;
use Illuminate\Support\Str;

/**
 * Trait for models that use slug-based URLs.
 *
 * Requires the model to have:
 * - 'slug' column
 * - A source field (default: 'title') for slug generation
 * - UUID primary key
 *
 * Supports two modes:
 * - With UUID (default): `title-abc12345` - guarantees uniqueness
 * - Without UUID: `title-of-article` - cleaner URLs, uses numeric suffix for collisions
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (self $model): void {
            if ($model->id === null) {
                $model->id = (string) Str::uuid();
            }

            if ($model->slug === null) {
                $model->slug = $model->generateUniqueSlug(
                    $model->{$model->getSlugSourceField()},
                    $model->id,
                );
            }
        });

        static::updating(function (self $model): void {
            $sourceField = $model->getSlugSourceField();

            if (! $model->isDirty($sourceField)) {
                return;
            }

            $oldSlug = $model->getOriginal('slug');
            $newSlug = $model->generateUniqueSlug($model->{$sourceField}, $model->id);

            if ($oldSlug === null || $oldSlug === $newSlug) {
                return;
            }

            app(SlugRedirectServiceInterface::class)->handleSlugChange(
                $oldSlug,
                $newSlug,
                $model->getSlugEntityType(),
                $model->id,
            );

            $model->slug = $newSlug;
        });
    }

    /**
     * Get the entity type identifier for slug redirects.
     */
    abstract public function getSlugEntityType(): string;

    /**
     * Get the source field for generating the slug.
     */
    protected function getSlugSourceField(): string
    {
        return 'title';
    }

    /**
     * Override to false for cleaner slugs without UUID suffix.
     * When false, collisions are handled with numeric suffix (-2, -3, etc.)
     */
    protected function slugIncludesUuid(): bool
    {
        return true;
    }

    /**
     * Generate a unique slug based on the configured mode.
     */
    private function generateUniqueSlug(string $title, string $uuid): string
    {
        if ($this->slugIncludesUuid()) {
            return $this->generateSlugWithUuid($title, $uuid);
        }

        return $this->generateSlugWithoutUuid($title, $uuid);
    }

    /**
     * Generate a slug with UUID suffix for guaranteed uniqueness.
     */
    private function generateSlugWithUuid(string $title, string $uuid): string
    {
        $length = 8;
        $slug = Slug::fromTitleAndUuid($title, $uuid, $length)->value;

        while ($this->slugExistsForOther($slug, $uuid) && $length < 32) {
            $length += 4;
            $slug = Slug::fromTitleAndUuid($title, $uuid, $length)->value;
        }

        return $slug;
    }

    /**
     * Generate a slug without UUID, using numeric suffix for collisions.
     */
    private function generateSlugWithoutUuid(string $title, string $excludeId): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'item';
        }

        $slug = $baseSlug;
        $counter = 2;

        while ($this->slugExistsForOther($slug, $excludeId)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if a slug exists for a different model.
     */
    private function slugExistsForOther(string $slug, string $excludeId): bool
    {
        return static::where('slug', $slug)
            ->where('id', '!=', $excludeId)
            ->exists();
    }
}
