<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

use App\Application\Media\DTOs\MediaAssetDTO;
use App\Domain\Media\Enums\MediaDestroyOutcome;
use App\Domain\Media\Exceptions\MediaCatalogUnavailableException;
use App\Domain\Media\ValueObjects\MediaFileId;

/**
 * What the remote store holds, and the only way to remove a file from it.
 */
interface MediaCatalogInterface
{
    /**
     * Every asset of the site, or an exception: a partial listing is never returned.
     *
     * @return list<MediaAssetDTO>
     *
     * @throws MediaCatalogUnavailableException
     */
    public function all(): array;

    /**
     * @throws MediaCatalogUnavailableException on any failure other than the asset not existing
     */
    public function find(MediaFileId $id, string $resourceType): ?MediaAssetDTO;

    /**
     * @throws MediaCatalogUnavailableException unless the store answered "ok" or "not found"
     */
    public function destroy(MediaFileId $id, string $resourceType): MediaDestroyOutcome;
}
