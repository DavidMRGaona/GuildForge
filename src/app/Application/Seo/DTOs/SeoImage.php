<?php

declare(strict_types=1);

namespace App\Application\Seo\DTOs;

final readonly class SeoImage
{
    public function __construct(
        public string $url,
        public ?int $width,
        public ?int $height,
        public string $alt,
        public bool $isLarge,
    ) {}

    /**
     * @return array{url: string, width: int|null, height: int|null, alt: string, isLarge: bool}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'isLarge' => $this->isLarge,
        ];
    }
}
