<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;

/**
 * What the core's footer blocks share (core#697): footer only, since their settings (rich text,
 * editor images) belong to the footer's stored JSON and its editor text source, and always available.
 */
abstract class CoreFooterBlock implements SiteBlockInterface
{
    public function placements(): array
    {
        return [SiteBlockPlacement::Footer];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * A switch of the block's settings; missing or not a boolean-like value means its default.
     *
     * @param  array<string, mixed>  $settings
     */
    protected static function enabled(array $settings, string $name, bool $default = true): bool
    {
        $value = $settings[$name] ?? null;

        return match (true) {
            is_bool($value) => $value,
            $value === 1, $value === '1' => true,
            $value === 0, $value === '0' => false,
            default => $default,
        };
    }

    /**
     * A text of the block's settings, trimmed; null when missing or blank.
     *
     * @param  array<string, mixed>  $settings
     */
    protected static function text(array $settings, string $name): ?string
    {
        $value = $settings[$name] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
