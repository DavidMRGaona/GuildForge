<?php

declare(strict_types=1);

namespace App\Infrastructure\Spreadsheets;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * A folder of one read or write alone, so the files openspout leaves (shared strings caches,
 * the parts of a workbook being written), which hold the sheet's content, never outlive the call.
 */
final readonly class TemporaryFolder
{
    private function __construct(
        public string $path,
    ) {}

    /**
     * @param  string|null  $root  Where to create it; the system's temporary folder by default
     */
    public static function create(?string $root = null): self
    {
        $path = rtrim($root ?? sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'gf-sheet-'.bin2hex(random_bytes(12));

        if (! mkdir($path, 0700) && ! is_dir($path)) {
            throw new RuntimeException('The temporary spreadsheet folder could not be created');
        }

        return new self($path);
    }

    public function delete(): void
    {
        if (! is_dir($this->path)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            /** @var \SplFileInfo $entry */
            $entry->isDir() && ! $entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($this->path);
    }
}
