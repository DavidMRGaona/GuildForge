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
        public ?string $description = null,
        public ?string $author = null,
        public ?array $requires = null,
        public ?array $dependencies = null,
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

        return new self(
            name: $data['name'],
            version: $data['version'],
            namespace: $data['namespace'],
            provider: $data['provider'],
            description: $data['description'] ?? null,
            author: $data['author'] ?? null,
            requires: $data['requires'] ?? [],
            dependencies: $data['dependencies'] ?? [],
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

        return $result;
    }
}
