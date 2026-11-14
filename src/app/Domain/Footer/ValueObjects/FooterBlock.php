<?php

declare(strict_types=1);

namespace App\Domain\Footer\ValueObjects;

/**
 * One block of a footer column: the site block key, an optional title drawn above it and the
 * block's own settings, as the panel stored them (core#697).
 */
final readonly class FooterBlock
{
    public const int MAX_TITLE_LENGTH = 80;

    public ?string $title;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $key,
        ?string $title,
        public array $settings,
    ) {
        $title = $title === null ? '' : trim($title);

        $this->title = $title === '' ? null : mb_substr($title, 0, self::MAX_TITLE_LENGTH);
    }

    /**
     * @return array{key: string, title: string|null, settings: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'settings' => $this->settings,
        ];
    }
}
