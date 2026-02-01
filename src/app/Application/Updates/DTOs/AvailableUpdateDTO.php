<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

use DateTimeImmutable;

/**
 * Represents an available update for a module.
 */
final readonly class AvailableUpdateDTO
{
    public function __construct(
        public string $moduleName,
        public string $displayName,
        public string $currentVersion,
        public string $availableVersion,
        public string $releaseNotes,
        public DateTimeImmutable $publishedAt,
        public bool $isPrerelease,
        public bool $isMajorUpdate,
        public string $downloadUrl,
        public bool $hasChecksum,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'module_name' => $this->moduleName,
            'display_name' => $this->displayName,
            'current_version' => $this->currentVersion,
            'available_version' => $this->availableVersion,
            'release_notes' => $this->releaseNotes,
            'published_at' => $this->publishedAt->format('c'),
            'is_prerelease' => $this->isPrerelease,
            'is_major_update' => $this->isMajorUpdate,
            'download_url' => $this->downloadUrl,
            'has_checksum' => $this->hasChecksum,
        ];
    }
}
