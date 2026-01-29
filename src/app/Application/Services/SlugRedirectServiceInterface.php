<?php

declare(strict_types=1);

namespace App\Application\Services;

interface SlugRedirectServiceInterface
{
    public function resolveCurrentSlug(string $slug, string $entityType): ?string;

    public function handleSlugChange(
        string $oldSlug,
        string $newSlug,
        string $entityType,
        string $entityId,
    ): void;
}
