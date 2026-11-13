<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation\SafetyNet;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

/**
 * Reads from the real adapter and discards every write, move, copy and delete. The logo, the
 * favicon and the images of the pages keep being read from the real disk.
 *
 * Paths under $writablePrefix (Livewire's temporary uploads, cleaned up on their own) still
 * reach the real disk, so a form with an upload keeps working; whatever the form saves goes
 * to another path and is discarded.
 */
final readonly class DiscardingFlysystemAdapter implements FilesystemAdapter
{
    public function __construct(
        private FilesystemAdapter $real,
        private ?string $writablePrefix = null,
    ) {}

    public function fileExists(string $path): bool
    {
        return $this->real->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->real->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        if ($this->isWritable($path)) {
            $this->real->write($path, $contents, $config);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        if ($this->isWritable($path)) {
            $this->real->writeStream($path, $contents, $config);
        }
    }

    public function read(string $path): string
    {
        return $this->real->read($path);
    }

    public function readStream(string $path)
    {
        return $this->real->readStream($path);
    }

    public function delete(string $path): void
    {
        if ($this->isWritable($path)) {
            $this->real->delete($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        if ($this->isWritable($path)) {
            $this->real->deleteDirectory($path);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        if ($this->isWritable($path)) {
            $this->real->createDirectory($path, $config);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if ($this->isWritable($path)) {
            $this->real->setVisibility($path, $visibility);
        }
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->real->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->real->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->real->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->real->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->real->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        if ($this->isWritable($source) && $this->isWritable($destination)) {
            $this->real->move($source, $destination, $config);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        if ($this->isWritable($destination)) {
            $this->real->copy($source, $destination, $config);
        }
    }

    private function isWritable(string $path): bool
    {
        if ($this->writablePrefix === null || $this->writablePrefix === '') {
            return false;
        }

        $path = ltrim($path, '/');

        return str_starts_with($path, $this->writablePrefix) && ! str_contains($path, '..');
    }
}
