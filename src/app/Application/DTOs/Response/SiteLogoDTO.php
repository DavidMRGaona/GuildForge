<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

/**
 * One of the site's own logos, as uploaded in the site settings.
 */
final readonly class SiteLogoDTO
{
    public function __construct(
        /** Storage path on the images disk (Cloudinary public ID). */
        public string $path,
        public string $url,
        /** Whether this is the dark-theme logo (light lines, made for a dark background). */
        public bool $isDark,
    ) {}

    /**
     * @return array{url: string, isDark: bool}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'isDark' => $this->isDark,
        ];
    }
}
