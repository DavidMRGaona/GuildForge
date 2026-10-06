<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

final readonly class ModuleRequirements
{
    /** Applied when a manifest declares no core constraint: 1.x modules keep loading on 2.x hosts only */
    public const string DEFAULT_CORE_CONSTRAINT = '^2.0';

    /**
     * @param  list<string>  $requiredModules
     * @param  list<string>  $requiredExtensions
     */
    public function __construct(
        private ?string $phpVersion,
        private ?string $laravelVersion,
        private array $requiredModules = [],
        private array $requiredExtensions = [],
        private ?string $coreVersion = null,
        private ?string $filamentVersion = null,
    ) {
    }

    /**
     * Database format: php_version, laravel_version, core_version, filament_version,
     * required_modules, required_extensions.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phpVersion: self::constraint($data, 'php_version'),
            laravelVersion: self::constraint($data, 'laravel_version'),
            requiredModules: self::strings($data, 'required_modules'),
            requiredExtensions: self::strings($data, 'required_extensions'),
            coreVersion: self::constraint($data, 'core_version'),
            filamentVersion: self::constraint($data, 'filament_version'),
        );
    }

    /**
     * Manifest format (module.json "requires"): php, laravel, core, filament, modules, extensions.
     * The input must come from ModuleManifestDTO::normalizeRequires(): a raw ['core' => null] would
     * read as an absent core (the ^2.0 default) instead of an invalid constraint.
     *
     * @param  array<string, mixed>  $requires
     */
    public static function fromManifest(array $requires): self
    {
        return new self(
            phpVersion: self::constraint($requires, 'php'),
            laravelVersion: self::constraint($requires, 'laravel'),
            requiredModules: self::strings($requires, 'modules'),
            requiredExtensions: self::strings($requires, 'extensions'),
            coreVersion: self::constraint($requires, 'core'),
            filamentVersion: self::constraint($requires, 'filament'),
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

    public function coreVersion(): ?string
    {
        return $this->coreVersion;
    }

    public function effectiveCoreConstraint(): string
    {
        return $this->coreVersion ?? self::DEFAULT_CORE_CONSTRAINT;
    }

    public function filamentVersion(): ?string
    {
        return $this->filamentVersion;
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

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /**
     * @return array{
     *     php_version: string|null,
     *     laravel_version: string|null,
     *     core_version: string|null,
     *     filament_version: string|null,
     *     required_modules: list<string>,
     *     required_extensions: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'php_version' => $this->phpVersion,
            'laravel_version' => $this->laravelVersion,
            'core_version' => $this->coreVersion,
            'filament_version' => $this->filamentVersion,
            'required_modules' => $this->requiredModules,
            'required_extensions' => $this->requiredExtensions,
        ];
    }

    /**
     * A present value that is not a string becomes '' (an invalid constraint), so a malformed
     * requirement fails closed instead of disappearing.
     *
     * @param  array<string, mixed>  $data
     */
    private static function constraint(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return is_string($value) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
