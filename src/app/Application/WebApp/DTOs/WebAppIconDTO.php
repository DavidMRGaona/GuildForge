<?php

declare(strict_types=1);

namespace App\Application\WebApp\DTOs;

/**
 * One square icon of the web app manifest: `any` is drawn as it is, `maskable` keeps its artwork inside
 * the safe zone on an opaque background, so the system may crop it to its own shape.
 */
final readonly class WebAppIconDTO
{
    public const string PURPOSE_ANY = 'any';

    public const string PURPOSE_MASKABLE = 'maskable';

    public function __construct(
        public string $src,
        public int $size,
        public string $type,
        public string $purpose,
    ) {}

    /**
     * @return array{src: string, sizes: string, type: string, purpose: string}
     */
    public function toArray(): array
    {
        return [
            'src' => $this->src,
            'sizes' => "{$this->size}x{$this->size}",
            'type' => $this->type,
            'purpose' => $this->purpose,
        ];
    }
}
