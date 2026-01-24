<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

final readonly class ModuleRequirements
{
    /**
     * @param  list<string>  $requiredModules
     * @param  list<string>  $requiredExtensions
     */
    public function __construct(
        private ?string $phpVersion,
        private ?string $laravelVersion,
        private array $requiredModules = [],
        private array $requiredExtensions = [],
    ) {}

    /**
     * @param array{
     *     php_version?: string|null,
     *     laravel_version?: string|null,
     *     required_modules?: list<string>,
     *     required_extensions?: list<string>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phpVersion: $data['php_version'] ?? null,
            laravelVersion: $data['laravel_version'] ?? null,
            requiredModules: $data['required_modules'] ?? [],
            requiredExtensions: $data['required_extensions'] ?? [],
        );
    }

    public function phpVersion(): ?string
    {
        return $this->phpVersion;
    }

    public function laravelVersion(): ?string
    {
        return $this->laravelVersion;
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return $this->requiredModules;
    }

    /**
     * @return list<string>
     */
    public function requiredExtensions(): array
    {
        return $this->requiredExtensions;
    }

    /**
     * @return array{
     *     php_version: string|null,
     *     laravel_version: string|null,
     *     required_modules: list<string>,
     *     required_extensions: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'php_version' => $this->phpVersion,
            'laravel_version' => $this->laravelVersion,
            'required_modules' => $this->requiredModules,
            'required_extensions' => $this->requiredExtensions,
        ];
    }

    /**
     * @param  list<string>  $availableModules
     * @param  list<string>  $availableExtensions
     */
    public function areSatisfied(
        string $phpVersion,
        string $laravelVersion,
        array $availableModules,
        array $availableExtensions,
    ): bool {
        return empty($this->getUnsatisfied(
            $phpVersion,
            $laravelVersion,
            $availableModules,
            $availableExtensions,
        ));
    }

    /**
     * @param  list<string>  $availableModules
     * @param  list<string>  $availableExtensions
     * @return list<string>
     */
    public function getUnsatisfied(
        string $phpVersion,
        string $laravelVersion,
        array $availableModules,
        array $availableExtensions,
    ): array {
        $unsatisfied = [];

        if ($this->phpVersion !== null && ! $this->versionSatisfiesConstraint($phpVersion, $this->phpVersion)) {
            $unsatisfied[] = "PHP version {$this->phpVersion} required, but {$phpVersion} found";
        }

        if ($this->laravelVersion !== null && ! $this->versionSatisfiesConstraint($laravelVersion, $this->laravelVersion)) {
            $unsatisfied[] = "Laravel version {$this->laravelVersion} required, but {$laravelVersion} found";
        }

        foreach ($this->requiredModules as $module) {
            if (! in_array($module, $availableModules, true)) {
                $unsatisfied[] = "Required module: {$module}";
            }
        }

        foreach ($this->requiredExtensions as $extension) {
            if (! in_array($extension, $availableExtensions, true)) {
                $unsatisfied[] = "Required extension: {$extension}";
            }
        }

        return $unsatisfied;
    }

    private function versionSatisfiesConstraint(string $version, string $constraint): bool
    {
        // Normalize version to semver format
        $normalizedVersion = $this->normalizeVersion($version);

        try {
            $moduleVersion = ModuleVersion::fromString($normalizedVersion);

            return $moduleVersion->satisfies($constraint);
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeVersion(string $version): string
    {
        // Extract just the version numbers (e.g., "8.3.0" from "8.3.0-dev")
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
            return "{$matches[1]}.{$matches[2]}.{$matches[3]}";
        }

        // Handle two-part versions (e.g., "8.3" -> "8.3.0")
        if (preg_match('/^(\d+)\.(\d+)$/', $version, $matches)) {
            return "{$matches[1]}.{$matches[2]}.0";
        }

        return $version;
    }
}
