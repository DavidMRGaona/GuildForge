<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

use InvalidArgumentException;

final readonly class ModuleManifestDTO
{
    /**
     * @param  array<string, mixed>|null  $requires
     * @param  array<string>|null  $dependencies
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $namespace,
        public string $provider,
        public ?string $displayName = null,
        public ?string $description = null,
        public ?string $author = null,
        public ?array $requires = null,
        public ?array $dependencies = null,
        public ?string $repository = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $required = ['name', 'version', 'namespace', 'provider'];
        foreach ($required as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $repository = $data['repository'] ?? null;
        if ($repository !== null && ! self::isValidRepository($repository)) {
            throw new InvalidArgumentException("Invalid repository, expected 'owner/repo'");
        }

        $requires = $data['requires'] ?? [];
        self::assertRequiresShape($requires);

        return new self(
            name: $data['name'],
            version: $data['version'],
            namespace: $data['namespace'],
            provider: $data['provider'],
            displayName: $data['displayName'] ?? null,
            description: $data['description'] ?? null,
            author: $data['author'] ?? null,
            requires: $requires,
            dependencies: $data['dependencies'] ?? [],
            repository: $repository,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'version' => $this->version,
            'namespace' => $this->namespace,
            'provider' => $this->provider,
        ];

        if ($this->displayName !== null) {
            $result['displayName'] = $this->displayName;
        }
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->author !== null) {
            $result['author'] = $this->author;
        }
        if ($this->requires !== null) {
            $result['requires'] = $this->requires;
        }
        if ($this->dependencies !== null) {
            $result['dependencies'] = $this->dependencies;
        }
        if ($this->repository !== null) {
            $result['repository'] = $this->repository;
        }

        return $result;
    }

    private const array CONSTRAINT_KEYS = ['php', 'laravel', 'core', 'filament'];

    private const array LIST_KEYS = ['modules', 'extensions'];

    /**
     * Makes a requires block safe to evaluate: a constraint with the wrong shape becomes ''
     * (an invalid constraint, so the module is rejected), lists keep only their strings, and
     * an unreadable block becomes an invalid core constraint instead of the default ^2.0.
     *
     * @return array{requires: array<string, mixed>, invalid: list<string>}
     */
    public static function normalizeRequires(mixed $requires): array
    {
        if ($requires === null) {
            return ['requires' => [], 'invalid' => []];
        }

        if (! is_array($requires) || ($requires !== [] && array_is_list($requires))) {
            return ['requires' => ['core' => ''], 'invalid' => ['requires']];
        }

        /** @var array<string, mixed> $requires */
        $invalid = [];

        foreach (self::CONSTRAINT_KEYS as $key) {
            if (array_key_exists($key, $requires) && ! is_string($requires[$key])) {
                $requires[$key] = '';
                $invalid[] = $key;
            }
        }

        foreach (self::LIST_KEYS as $key) {
            if (! array_key_exists($key, $requires)) {
                continue;
            }

            if (! self::isListOfStrings($requires[$key])) {
                $invalid[] = $key;
            }

            $requires[$key] = is_array($requires[$key]) ? array_values(array_filter($requires[$key], 'is_string')) : [];
        }

        return ['requires' => $requires, 'invalid' => $invalid];
    }

    private static function assertRequiresShape(mixed $requires): void
    {
        if (! is_array($requires) || ($requires !== [] && array_is_list($requires))) {
            throw new InvalidArgumentException('Invalid requires');
        }

        foreach (self::CONSTRAINT_KEYS as $key) {
            if (array_key_exists($key, $requires) && ! is_string($requires[$key])) {
                throw new InvalidArgumentException("Invalid requires.{$key}");
            }
        }

        foreach (self::LIST_KEYS as $key) {
            if (array_key_exists($key, $requires) && ! self::isListOfStrings($requires[$key])) {
                throw new InvalidArgumentException("Invalid requires.{$key}");
            }
        }
    }

    private static function isListOfStrings(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, 'is_string') === $value;
    }

    /**
     * A GitHub repository in "owner/repo" form.
     */
    public static function isValidRepository(mixed $repository): bool
    {
        return is_string($repository) && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) === 1;
    }

    public function repositoryOwner(): ?string
    {
        return $this->repository === null ? null : explode('/', $this->repository, 2)[0];
    }

    public function repositoryName(): ?string
    {
        return $this->repository === null ? null : explode('/', $this->repository, 2)[1];
    }
}
