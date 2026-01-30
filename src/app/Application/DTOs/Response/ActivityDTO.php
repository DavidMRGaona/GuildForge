<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class ActivityDTO
{
    public function __construct(
        public string $icon,
        public string $title,
        public string $description,
    ) {
    }

    /**
     * Create from array.
     *
     * @param  array{icon: string, title: string, description: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            icon: $data['icon'],
            title: $data['title'],
            description: $data['description'],
        );
    }

    /**
     * Convert to array for frontend consumption.
     *
     * @return array{icon: string, title: string, description: string}
     */
    public function toArray(): array
    {
        return [
            'icon' => $this->icon,
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
