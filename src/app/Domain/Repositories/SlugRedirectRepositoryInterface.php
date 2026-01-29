<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\SlugRedirect;
use App\Domain\ValueObjects\Slug;

interface SlugRedirectRepositoryInterface
{
    public function findByOldSlugAndType(Slug $oldSlug, string $entityType): ?SlugRedirect;

    public function save(SlugRedirect $redirect): void;

    public function updateAllPointingTo(Slug $oldTarget, Slug $newTarget, string $entityType): void;

    public function deleteByEntityId(string $entityId, string $entityType): void;
}
