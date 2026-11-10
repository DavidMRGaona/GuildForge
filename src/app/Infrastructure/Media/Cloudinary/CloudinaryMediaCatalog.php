<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Cloudinary;

use App\Application\Media\DTOs\MediaAssetDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Domain\Media\Enums\MediaDestroyOutcome;
use App\Domain\Media\Exceptions\MediaCatalogUnavailableException;
use App\Domain\Media\ValueObjects\MediaFileId;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Cloudinary;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Cloudinary admin/upload API behind the media library. Only `image` resources are listed:
 * every upload goes through the disk as an image, and raw public ids keep their extension in
 * Cloudinary while stored paths normalize without it, so a used raw file could look unused.
 */
final readonly class CloudinaryMediaCatalog implements MediaCatalogInterface
{
    private const string LISTED_RESOURCE_TYPE = 'image';

    private const int PAGE_SIZE = 500;

    public function __construct(
        private Cloudinary $cloudinary,
        private MediaFileIdResolverInterface $resolver,
    ) {}

    public function all(): array
    {
        $assets = [];
        $cursor = null;

        try {
            do {
                $options = [
                    'type' => 'upload',
                    'resource_type' => self::LISTED_RESOURCE_TYPE,
                    'prefix' => $this->resolver->prefix().'/',
                    'max_results' => self::PAGE_SIZE,
                ];

                if ($cursor !== null) {
                    $options['next_cursor'] = $cursor;
                }

                $response = $this->cloudinary->adminApi()->assets($options);
                $resources = $response['resources'] ?? null;

                if (! is_array($resources)) {
                    throw MediaCatalogUnavailableException::because('malformed listing');
                }

                foreach ($resources as $resource) {
                    $asset = is_array($resource) ? $this->toDto($resource, self::LISTED_RESOURCE_TYPE) : null;

                    if ($asset instanceof MediaAssetDTO) {
                        $assets[] = $asset;
                    }
                }

                $next = $response['next_cursor'] ?? null;
                $cursor = is_string($next) && $next !== '' ? $next : null;
            } while ($cursor !== null);
        } catch (MediaCatalogUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw MediaCatalogUnavailableException::because('listing failed ('.$e::class.')', $e);
        }

        return $assets;
    }

    public function find(MediaFileId $id, string $resourceType): ?MediaAssetDTO
    {
        try {
            $response = $this->cloudinary->adminApi()->asset($this->publicId($id), ['resource_type' => $resourceType]);

            return $this->toDto($response->getArrayCopy(), $resourceType);
        } catch (NotFound) {
            return null;
        } catch (MediaCatalogUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw MediaCatalogUnavailableException::because('lookup failed ('.$e::class.')', $e);
        }
    }

    public function destroy(MediaFileId $id, string $resourceType): MediaDestroyOutcome
    {
        try {
            $response = $this->cloudinary->uploadApi()->destroy($this->publicId($id), [
                'resource_type' => $resourceType,
                'invalidate' => true,
            ]);
            $result = $response['result'] ?? null;
        } catch (NotFound) {
            return MediaDestroyOutcome::Missing;
        } catch (Throwable $e) {
            throw MediaCatalogUnavailableException::because('delete failed ('.$e::class.')', $e);
        }

        return match ($result) {
            'ok' => MediaDestroyOutcome::Deleted,
            'not found' => MediaDestroyOutcome::Missing,
            default => throw MediaCatalogUnavailableException::because(
                'unexpected delete result '.(is_string($result) ? "'{$result}'" : 'of another type'),
            ),
        };
    }

    private function publicId(MediaFileId $id): string
    {
        $prefix = $this->resolver->prefix();

        return $prefix === '' ? $id->value : $prefix.'/'.$id->value;
    }

    /**
     * @param  array<array-key, mixed>  $resource
     */
    private function toDto(array $resource, string $resourceType): ?MediaAssetDTO
    {
        $publicId = $resource['public_id'] ?? null;
        $id = is_string($publicId) ? $this->resolver->fromPublicId($publicId) : null;

        if (! $id instanceof MediaFileId) {
            return null;
        }

        $createdAt = $resource['created_at'] ?? null;

        if (! is_string($createdAt)) {
            throw MediaCatalogUnavailableException::because('asset without a creation date');
        }

        try {
            $uploadedAt = (new DateTimeImmutable($createdAt))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            throw MediaCatalogUnavailableException::because('asset with an unreadable creation date', $e);
        }

        return new MediaAssetDTO(
            id: $id,
            resourceType: $resourceType,
            format: is_string($resource['format'] ?? null) ? $resource['format'] : null,
            bytes: is_numeric($resource['bytes'] ?? null) ? (int) $resource['bytes'] : 0,
            width: is_numeric($resource['width'] ?? null) ? (int) $resource['width'] : null,
            height: is_numeric($resource['height'] ?? null) ? (int) $resource['height'] : null,
            uploadedAt: $uploadedAt,
        );
    }
}
