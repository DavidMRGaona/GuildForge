<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Domain\Media\Exceptions\InvalidMediaFileIdException;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Storage\Cloudinary\CloudinaryPathMapper;

final readonly class CloudinaryMediaFileIdResolver implements MediaFileIdResolverInterface
{
    private string $prefix;

    private CloudinaryPathMapper $mapper;

    public function __construct(?string $prefix = null)
    {
        $this->prefix = $prefix !== null ? trim(str_replace('\\', '/', $prefix), '/') : '';
        $this->mapper = new CloudinaryPathMapper($this->prefix);
    }

    public function fromStoredPath(string $path): ?MediaFileId
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            $path = $this->pathFromUrl($path);

            if ($path === null) {
                return null;
            }
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            return null;
        }

        return $this->fromPublicId($this->mapper->toResource($path)[0]);
    }

    public function fromPublicId(string $prefixedPublicId): ?MediaFileId
    {
        $stripped = $prefixedPublicId;

        if ($this->prefix !== '') {
            if (! str_starts_with($prefixedPublicId, $this->prefix.'/')) {
                return null;
            }

            $stripped = substr($prefixedPublicId, strlen($this->prefix) + 1);
        }

        try {
            return new MediaFileId($stripped);
        } catch (InvalidMediaFileIdException) {
            return null;
        }
    }

    public function resourceType(string $storedPath): string
    {
        return $this->mapper->toResource($storedPath)[1];
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Path (with the prefix, with extension) of a delivery URL of this site; null for another
     * host family or prefix.
     */
    private function pathFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $urlPath = parse_url($url, PHP_URL_PATH);

        if (! is_string($host) || ! is_string($urlPath) || ! str_ends_with(strtolower($host), 'cloudinary.com')) {
            return null;
        }

        $marker = strpos($urlPath, '/upload/');

        if ($marker === false) {
            return null;
        }

        $segments = explode('/', substr($urlPath, $marker + strlen('/upload/')));

        // Transformation sets ("c_fill,w_300") and the version ("v12") come before the public id
        while (count($segments) > 1 && ! $this->startsWithPrefix(implode('/', $segments)) && $this->isDeliveryDecoration($segments[0])) {
            array_shift($segments);
        }

        $segments = array_map(rawurldecode(...), $segments);
        $rest = implode('/', $segments);

        if ($this->prefix !== '' && ! str_starts_with($rest, $this->prefix.'/')) {
            return null;
        }

        return $rest;
    }

    private function startsWithPrefix(string $path): bool
    {
        return $this->prefix !== '' && str_starts_with($path, $this->prefix.'/');
    }

    private function isDeliveryDecoration(string $segment): bool
    {
        return preg_match('/^v\d+$/', $segment) === 1
            || str_contains($segment, ',')
            || preg_match('/^[a-z]{1,3}_[^\/]*$/i', $segment) === 1;
    }
}
