<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\ValueObjects\ModuleVersion;
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
        public ?DateTimeImmutable $publishedAt,
        public bool $isPrerelease,
        public bool $isMajorUpdate,
        public string $downloadUrl,
        public bool $hasChecksum,
    ) {}

    /**
     * Pending update as persisted by the last check, without querying GitHub.
     * Release details (notes, date, URL) are only known after a fresh check.
     */
    public static function fromModule(Module $module): self
    {
        $current = $module->version();
        $available = ModuleVersion::fromString((string) $module->latestAvailableVersion());

        return new self(
            moduleName: $module->name()->value,
            displayName: $module->displayName(),
            currentVersion: $current->value(),
            availableVersion: $available->value(),
            releaseNotes: '',
            publishedAt: null,
            isPrerelease: $available->preRelease !== null,
            isMajorUpdate: $available->major > $current->major,
            downloadUrl: '',
            hasChecksum: false,
        );
    }

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
            'published_at' => $this->publishedAt?->format('c'),
            'is_prerelease' => $this->isPrerelease,
            'is_major_update' => $this->isMajorUpdate,
            'download_url' => $this->downloadUrl,
            'has_checksum' => $this->hasChecksum,
        ];
    }
}
