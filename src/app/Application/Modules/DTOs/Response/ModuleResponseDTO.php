<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs\Response;

final readonly class ModuleResponseDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $status,
        public ?string $description = null,
        public ?string $author = null,
        public ?string $enabledAt = null,
        public ?string $discoveredAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            version: $data['version'],
            status: $data['status'],
            description: $data['description'] ?? null,
            author: $data['author'] ?? null,
            enabledAt: $data['enabled_at'] ?? null,
            discoveredAt: $data['discovered_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'status' => $this->status,
            'description' => $this->description,
            'author' => $this->author,
            'enabled_at' => $this->enabledAt,
            'discovered_at' => $this->discoveredAt,
        ];
    }
}
