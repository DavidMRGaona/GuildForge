<?php

declare(strict_types=1);

namespace App\Infrastructure\Footer;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Footer\ValueObjects\FooterLayout;
use JsonException;

/**
 * The footer layout in the settings row `footer_layout`. Reads go through the settings cache, which
 * every save forgets, so the footer changes on the next page view.
 */
final readonly class SettingsFooterLayoutService implements FooterLayoutServiceInterface
{
    /** Slashes and accents as they are: the media library and the editor image mover read the stored text */
    public const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function layout(): FooterLayout
    {
        return self::decode($this->settings->get(self::SETTING_KEY));
    }

    public function save(FooterLayout $layout): void
    {
        $this->settings->set(self::SETTING_KEY, $layout->isEmpty() ? '' : self::encode($layout->toArray()));
    }

    public static function decode(mixed $stored): FooterLayout
    {
        if (! is_string($stored) || $stored === '') {
            return FooterLayout::empty();
        }

        try {
            return FooterLayout::fromArray(json_decode($stored, true, 32, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return FooterLayout::empty();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    public static function encode(array $data): string
    {
        return json_encode($data, self::JSON_FLAGS);
    }
}
