<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class DownloadLink
{
    public function __construct(
        public string $label,
        public string $url,
        public string $description,
    ) {}

    /**
     * @param  array{label: string, url: string, description: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            url: $data['url'],
            description: $data['description'],
        );
    }

    /**
     * @return array{label: string, url: string, description: string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'description' => $this->description,
        ];
    }
}
