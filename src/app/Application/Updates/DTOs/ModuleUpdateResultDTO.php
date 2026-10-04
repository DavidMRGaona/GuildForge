<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

use App\Domain\Updates\Enums\UpdateStatus;

/**
 * Result of a module update operation.
 */
final readonly class ModuleUpdateResultDTO
{
    /**
     * @param  array<string>  $migrationsRun
     * @param  array<string>  $seedersRun
     */
    public function __construct(
        public string $moduleName,
        public string $fromVersion,
        public string $toVersion,
        public UpdateStatus $status,
        public array $migrationsRun,
        public array $seedersRun,
        public ?string $errorMessage,
        public ?string $backupPath,
        public string $historyId,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === UpdateStatus::Completed;
    }

    public function wasRolledBack(): bool
    {
        return $this->status === UpdateStatus::RolledBack;
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
            'status' => $this->status->value,
            'migrations_run' => $this->migrationsRun,
            'seeders_run' => $this->seedersRun,
            'error_message' => $this->errorMessage,
            'backup_path' => $this->backupPath,
            'history_id' => $this->historyId,
        ];
    }
}
