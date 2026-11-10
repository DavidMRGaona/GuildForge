<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Cloudinary;

use App\Application\Media\DTOs\MediaAssetDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Domain\Media\Enums\MediaDestroyOutcome;
use App\Domain\Media\Exceptions\MediaCatalogUnavailableException;
use App\Domain\Media\ValueObjects\MediaFileId;
use Closure;
use Throwable;

/**
 * Builds the real catalog on first use. The Cloudinary SDK refuses to build its client without valid
 * settings (local development, a fresh install, CI without credentials): resolving the catalog must never
 * fail for that, and using it then fails like an unreachable catalog, so callers fail closed.
 */
final class DeferredMediaCatalog implements MediaCatalogInterface
{
    private ?MediaCatalogInterface $catalog = null;

    /**
     * @param  Closure(): MediaCatalogInterface  $factory
     */
    public function __construct(
        private readonly Closure $factory,
    ) {}

    public function all(): array
    {
        return $this->catalog()->all();
    }

    public function find(MediaFileId $id, string $resourceType): ?MediaAssetDTO
    {
        return $this->catalog()->find($id, $resourceType);
    }

    public function destroy(MediaFileId $id, string $resourceType): MediaDestroyOutcome
    {
        return $this->catalog()->destroy($id, $resourceType);
    }

    private function catalog(): MediaCatalogInterface
    {
        if ($this->catalog instanceof MediaCatalogInterface) {
            return $this->catalog;
        }

        try {
            return $this->catalog = ($this->factory)();
        } catch (Throwable $e) {
            throw MediaCatalogUnavailableException::because('client could not be built ('.$e::class.')', $e);
        }
    }
}
