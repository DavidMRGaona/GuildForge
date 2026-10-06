<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;

/**
 * A newer release this host cannot run, as recorded by the last check. Shown, never applied.
 */
final readonly class BlockedReleaseDTO
{
    /**
     * @param  list<CompatibilityIssue>  $issues
     */
    public function __construct(
        public string $moduleName,
        public string $displayName,
        public string $currentVersion,
        public string $blockedVersion,
        public array $issues,
    ) {
    }

    public static function fromModule(Module $module): self
    {
        return new self(
            moduleName: $module->name()->value,
            displayName: $module->displayName(),
            currentVersion: $module->version()->value(),
            blockedVersion: (string) $module->latestBlockedVersion(),
            issues: $module->latestBlockedIssues(),
        );
    }

    /**
     * Livewire public properties hold arrays, not DTOs.
     *
     * @return array{module_name: string, display_name: string, current_version: string, blocked_version: string, issues: list<array{requirement: string, required: string, found: string|null, reason_key: string}>}
     */
    public function toArray(): array
    {
        return [
            'module_name' => $this->moduleName,
            'display_name' => $this->displayName,
            'current_version' => $this->currentVersion,
            'blocked_version' => $this->blockedVersion,
            'issues' => array_map(static fn (CompatibilityIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
