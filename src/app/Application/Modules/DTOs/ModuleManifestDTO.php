<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

use InvalidArgumentException;

final readonly class ModuleManifestDTO
{
    /**
     * @param  array<string, string>|null  $requires
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
        if ($repository !== null && (! is_string($repository) || preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) !== 1)) {
            throw new InvalidArgumentException("Invalid repository, expected 'owner/repo'");
        }

        return new self(
            name: $data['name'],
            version: $data['version'],
            namespace: $data['namespace'],
            provider: $data['provider'],
            displayName: $data['displayName'] ?? null,
            description: $data['description'] ?? null,
            author: $data['author'] ?? null,
            requires: $data['requires'] ?? [],
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

    public function repositoryOwner(): ?string
    {
        return $this->repository === null ? null : explode('/', $this->repository, 2)[0];
    }

    public function repositoryName(): ?string
    {
        return $this->repository === null ? null : explode('/', $this->repository, 2)[1];
    }
}
