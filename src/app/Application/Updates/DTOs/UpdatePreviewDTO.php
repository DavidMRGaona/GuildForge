<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

/**
 * Preview of what an update will change before applying it.
 */
final readonly class UpdatePreviewDTO
{
    /**
     * @param  array<string>  $pendingMigrations
     * @param  array<string>  $newSeeders
     */
    public function __construct(
        public string $moduleName,
        public string $fromVersion,
        public string $toVersion,
        public array $pendingMigrations,
        public array $newSeeders,
        public string $changelog,
        public bool $isMajorUpdate,
        public bool $coreCompatible,
        public ?string $coreRequirement,
        public ?string $downloadUrl,
        public ?int $downloadSize,
    ) {
    }

    public function hasMigrations(): bool
    {
        return ! empty($this->pendingMigrations);
    }

    public function hasSeeders(): bool
    {
        return ! empty($this->newSeeders);
    }

    public function hasBreakingChanges(): bool
    {
        return $this->isMajorUpdate || ! $this->coreCompatible;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'module_name' => $this->moduleName,
            'from_version' => $this->fromVersion,
            'to_version' => $this->toVersion,
            'pending_migrations' => $this->pendingMigrations,
            'new_seeders' => $this->newSeeders,
            'changelog' => $this->changelog,
            'is_major_update' => $this->isMajorUpdate,
            'core_compatible' => $this->coreCompatible,
            'core_requirement' => $this->coreRequirement,
            'download_url' => $this->downloadUrl,
            'download_size' => $this->downloadSize,
        ];
    }
}
