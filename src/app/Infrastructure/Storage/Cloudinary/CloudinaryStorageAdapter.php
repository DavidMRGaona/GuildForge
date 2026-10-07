<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Cloudinary;

use App\Application\Services\ImageOptimizationServiceInterface;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Cloudinary;
use DateTimeImmutable;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use Throwable;

/**
 * Flysystem adapter for Cloudinary, built on cloudinary/cloudinary_php only.
 *
 * Differences with the adapter it derives from: the prefix applies to every
 * resource type, URLs are built locally (no Admin API call), images are
 * optimized and uploaded with their asset folder, a missing asset is not a
 * delete error, and fileExists() answers true, with a warning, when the Admin
 * API or the transport fails (a rate-limited answer must not make Filament drop
 * the stored path, which would delete the image on save). Upload errors reach
 * the caller unchanged: Laravel turns UnableToWriteFile into a silent false on
 * disks without "throw".
 *
 * Derived from CloudinaryLabs\CloudinaryLaravel\CloudinaryStorageAdapter
 * (cloudinary-labs/cloudinary-laravel 3.0.2,
 * https://github.com/cloudinary-community/cloudinary-laravel/tree/3.0.2),
 * distributed under this license:
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2025 Cloudinary Labs
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
final class CloudinaryStorageAdapter implements ChecksumProvider, FilesystemAdapter
{
    public function __construct(
        private readonly Cloudinary $cloudinary,
        private readonly CloudinaryPathMapper $paths,
        private readonly ?ImageOptimizationServiceInterface $imageOptimization = null,
    ) {
    }

    public function getUrl(string $path): string
    {
        [$publicId, $type] = $this->paths->toResource($path);

        return $type === 'video'
            ? (string) $this->cloudinary->video($publicId)->toUrl()
            : (string) $this->cloudinary->image($publicId)->toUrl();
    }

    public function fileExists(string $path): bool
    {
        [$publicId, $type] = $this->paths->toResource($path);

        try {
            $this->cloudinary->adminApi()->asset($publicId, ['resource_type' => $type]);

            return true;
        } catch (NotFound) {
            return false;
        } catch (ApiError|TransferException $e) {
            // The message is not logged: transport errors can carry the request URL.
            Log::warning('Cloudinary existence check failed; assuming the file exists', [
                'path' => $path,
                'exception' => $e::class,
            ]);

            return true;
        }
    }

    public function directoryExists(string $path): bool
    {
        return $this->fileExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $mimeType = $this->paths->mimeType($path);

        if ($this->imageOptimization !== null) {
            $contents = $this->imageOptimization->optimize($contents, $mimeType);
        }

        [$publicId, $type] = $this->paths->toResource($path);

        $this->cloudinary->uploadApi()->upload(
            'data:'.($mimeType ?? 'application/octet-stream').';base64,'.base64_encode($contents),
            ['public_id' => $publicId, 'resource_type' => $type, 'asset_folder' => $this->folderOf($publicId)],
        );
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $string = is_resource($contents) ? stream_get_contents($contents) : (string) $contents;

        $this->write($path, $string === false ? '' : $string, $config);
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($this->secureUrl($path));

        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $stream = @fopen($this->secureUrl($path), 'rb');

        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $stream;
    }

    public function delete(string $path): void
    {
        [$publicId, $type] = $this->paths->toResource($path);

        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId, ['resource_type' => $type]);
        } catch (NotFound) {
            return;
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }

        $status = $result['result'] ?? null;

        if ($status !== 'ok' && $status !== 'not found') {
            throw UnableToDeleteFile::atLocation($path, is_string($status) ? $status : 'unknown result');
        }
    }

    public function deleteDirectory(string $path): void
    {
        $this->cloudinary->adminApi()->deleteAssetsByPrefix($this->paths->prefixed($path));
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->cloudinary->adminApi()->createFolder($this->paths->prefixed($path));
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Cloudinary does not support visibility.');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, mimeType: $this->paths->mimeType($path));
    }

    public function lastModified(string $path): FileAttributes
    {
        $createdAt = $this->asset($path)['created_at'] ?? null;

        return new FileAttributes($path, lastModified: is_string($createdAt) ? (new DateTimeImmutable($createdAt))->getTimestamp() : null);
    }

    public function fileSize(string $path): FileAttributes
    {
        $bytes = $this->asset($path)['bytes'] ?? null;

        return new FileAttributes($path, fileSize: is_int($bytes) ? $bytes : null);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $cursor = null;

        do {
            $response = $this->cloudinary->adminApi()->assets(array_filter([
                'type' => 'upload',
                'prefix' => $this->paths->prefixed($path),
                'max_results' => 500,
                'next_cursor' => $cursor,
            ], static fn (mixed $value): bool => $value !== null));

            foreach ($response['resources'] ?? [] as $resource) {
                yield new FileAttributes(
                    (string) $resource['public_id'],
                    isset($resource['bytes']) ? (int) $resource['bytes'] : null,
                    null,
                    isset($resource['created_at']) ? (new DateTimeImmutable((string) $resource['created_at']))->getTimestamp() : null,
                );
            }

            $cursor = $response['next_cursor'] ?? null;
        } while ($cursor !== null);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }

    public function checksum(string $path, Config $config): string
    {
        $algorithm = $config->get('checksum_algo', 'sha256');

        return hash(is_string($algorithm) ? $algorithm : 'sha256', $this->read($path));
    }

    /**
     * @return array<string, mixed>
     */
    private function asset(string $path): array
    {
        [$publicId, $type] = $this->paths->toResource($path);

        try {
            return $this->cloudinary->adminApi()->asset($publicId, ['resource_type' => $type])->getArrayCopy();
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::create($path, 'metadata', $e->getMessage(), $e);
        }
    }

    private function secureUrl(string $path): string
    {
        $url = $this->asset($path)['secure_url'] ?? null;

        if (! is_string($url)) {
            throw UnableToReadFile::fromLocation($path, 'Missing secure_url');
        }

        return $url;
    }

    private function folderOf(string $publicId): string
    {
        $slash = strrpos($publicId, '/');

        return $slash === false ? '' : substr($publicId, 0, $slash);
    }
}
