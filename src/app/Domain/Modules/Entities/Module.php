<?php

declare(strict_types=1);

namespace App\Domain\Modules\Entities;

use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use DateTimeImmutable;

final class Module
{
    /**
     * @param  array<string>  $dependencies
     */
    public function __construct(
        private readonly ModuleId $id,
        private readonly ModuleName $name,
        private readonly string $displayName,
        private readonly string $description,
        private readonly ModuleVersion $version,
        private readonly string $author,
        private readonly ModuleRequirements $requirements,
        private ModuleStatus $status,
        private ?DateTimeImmutable $enabledAt = null,
        private ?DateTimeImmutable $installedAt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
        private readonly ?string $namespace = null,
        private readonly ?string $provider = null,
        private readonly ?string $path = null,
        private readonly array $dependencies = [],
    ) {}

    public function id(): ModuleId
    {
        return $this->id;
    }

    public function name(): ModuleName
    {
        return $this->name;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function version(): ModuleVersion
    {
        return $this->version;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function requirements(): ModuleRequirements
    {
        return $this->requirements;
    }

    public function status(): ModuleStatus
    {
        return $this->status;
    }

    public function enabledAt(): ?DateTimeImmutable
    {
        return $this->enabledAt;
    }

    public function installedAt(): ?DateTimeImmutable
    {
        return $this->installedAt;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function namespace(): string
    {
        return $this->namespace ?? $this->generateDefaultNamespace();
    }

    public function provider(): string
    {
        return $this->provider ?? 'ModuleServiceProvider';
    }

    public function path(): string
    {
        return $this->path ?? $this->generateDefaultPath();
    }

    /**
     * Get direct module dependencies (simpler than requirements).
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function enable(): void
    {
        $this->status = ModuleStatus::Enabled;
        $this->enabledAt = new DateTimeImmutable;
    }

    public function disable(): void
    {
        $this->status = ModuleStatus::Disabled;
        $this->enabledAt = null;
    }

    public function isEnabled(): bool
    {
        return $this->status === ModuleStatus::Enabled;
    }

    public function isDisabled(): bool
    {
        return $this->status === ModuleStatus::Disabled;
    }

    public function isInstalled(): bool
    {
        return $this->installedAt !== null;
    }

    public function markInstalled(): void
    {
        $this->installedAt = new DateTimeImmutable;
    }

    public function markUninstalled(): void
    {
        $this->installedAt = null;
    }

    /**
     * @param  list<string>  $availableModules
     * @param  list<string>  $availableExtensions
     */
    public function requirementsSatisfied(
        string $phpVersion,
        string $laravelVersion,
        array $availableModules,
        array $availableExtensions,
    ): bool {
        return $this->requirements->areSatisfied(
            $phpVersion,
            $laravelVersion,
            $availableModules,
            $availableExtensions,
        );
    }

    /**
     * @param  list<string>  $availableModules
     * @param  list<string>  $availableExtensions
     * @return list<string>
     */
    public function getUnsatisfiedRequirements(
        string $phpVersion,
        string $laravelVersion,
        array $availableModules,
        array $availableExtensions,
    ): array {
        return $this->requirements->getUnsatisfied(
            $phpVersion,
            $laravelVersion,
            $availableModules,
            $availableExtensions,
        );
    }

    /**
     * Generate default namespace from module name.
     * Converts kebab-case to PascalCase (e.g., 'my-module' -> 'Modules\MyModule').
     */
    private function generateDefaultNamespace(): string
    {
        $pascalCase = str_replace(' ', '', ucwords(str_replace('-', ' ', $this->name->value)));

        return 'Modules\\'.$pascalCase;
    }

    /**
     * Generate default path from module name.
     */
    private function generateDefaultPath(): string
    {
        return base_path('modules/'.$this->name->value);
    }
}
