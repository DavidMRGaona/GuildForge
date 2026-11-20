<?php

declare(strict_types=1);

namespace App\Application\WebApp\DTOs;

/**
 * The web app manifest (core#802): the same for everybody, never anything about the account.
 * The app lives at the root, so its id, start URL and scope are fixed.
 */
final readonly class WebAppManifestDTO
{
    /**
     * @param  list<WebAppIconDTO>  $icons
     */
    public function __construct(
        public string $name,
        public string $lang,
        public string $themeColor,
        public string $backgroundColor,
        public array $icons,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => '/',
            'name' => $this->name,
            'short_name' => $this->name,
            'lang' => $this->lang,
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => $this->themeColor,
            'background_color' => $this->backgroundColor,
            'icons' => array_map(static fn (WebAppIconDTO $icon): array => $icon->toArray(), $this->icons),
        ];
    }
}
