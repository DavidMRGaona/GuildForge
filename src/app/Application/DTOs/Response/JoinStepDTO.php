<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class JoinStepDTO
{
    public function __construct(
        public string $title,
        public ?string $description,
    ) {
    }

    /**
     * Create from array.
     *
     * @param  array{title: string, description?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
        );
    }

    /**
     * Convert to array for frontend consumption.
     *
     * @return array{title: string, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
