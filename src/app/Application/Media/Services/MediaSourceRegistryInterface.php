<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Domain\Media\ValueObjects\MediaFileId;

interface MediaSourceRegistryInterface
{
    /**
     * @param  class-string  $sourceClass
     */
    public function register(string $sourceClass, ?string $module = null): void;

    /**
     * @param  array<class-string>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void;

    public function unregisterModule(string $module): void;

    public function clear(): void;

    /**
     * Resolved sources in registration order, one per type().
     *
     * @return list<MediaUsageSourceInterface>
     */
    public function sources(): array;

    public function source(string $type): ?MediaUsageSourceInterface;

    /**
     * The source whose claimed folder is the longest prefix of the file's path, ignoring matches
     * that fall inside one of that source's excluded subfolders.
     */
    public function claimingSource(MediaFileId $file): ?MediaUsageSourceInterface;

    /**
     * Module that registered the source of this type, or null for core (or an unknown type).
     */
    public function moduleOf(string $type): ?string;

    /**
     * Folders claimed by hidden sources.
     *
     * @return list<string>
     */
    public function hiddenFolders(): array;
}
