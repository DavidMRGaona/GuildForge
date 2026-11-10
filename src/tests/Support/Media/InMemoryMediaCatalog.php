<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\DTOs\MediaAssetDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Domain\Media\Enums\MediaDestroyOutcome;
use App\Domain\Media\Exceptions\MediaCatalogUnavailableException;
use App\Domain\Media\ValueObjects\MediaFileId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Bound for every test by Tests\TestCase: the real catalog calls Cloudinary's admin API, and saving any
 * record with an image refreshes its media usages (synchronously with the test queue), which may look a file up.
 */
final class InMemoryMediaCatalog implements MediaCatalogInterface
{
    /** @var array<string, MediaAssetDTO> */
    public array $assets = [];

    /** Makes every call fail as an unreachable catalog would */
    public bool $unavailable = false;

    /** @var list<string> Ids looked up with find(), in order */
    public array $lookups = [];

    public function add(string $id, int $bytes = 1000, string $uploadedAt = '2026-11-01 10:00:00'): MediaAssetDTO
    {
        $asset = new MediaAssetDTO(
            id: new MediaFileId($id),
            resourceType: 'image',
            format: 'jpg',
            bytes: $bytes,
            width: 800,
            height: 600,
            uploadedAt: new DateTimeImmutable($uploadedAt, new DateTimeZone('UTC')),
        );
        $this->assets[$id] = $asset;

        return $asset;
    }

    public function all(): array
    {
        $this->failIfUnavailable();

        return array_values($this->assets);
    }

    public function find(MediaFileId $id, string $resourceType): ?MediaAssetDTO
    {
        $this->lookups[] = $id->value;
        $this->failIfUnavailable();

        $asset = $this->assets[$id->value] ?? null;

        return $asset !== null && $asset->resourceType === $resourceType ? $asset : null;
    }

    public function destroy(MediaFileId $id, string $resourceType): MediaDestroyOutcome
    {
        $this->failIfUnavailable();

        if (! isset($this->assets[$id->value])) {
            return MediaDestroyOutcome::Missing;
        }

        unset($this->assets[$id->value]);

        return MediaDestroyOutcome::Deleted;
    }

    private function failIfUnavailable(): void
    {
        if ($this->unavailable) {
            throw MediaCatalogUnavailableException::because('test catalog unavailable');
        }
    }
}
