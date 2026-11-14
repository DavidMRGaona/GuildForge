<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Domain\SiteBlocks\LinkTargetRule;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Closure;
use Filament\Forms\Components\TextInput;

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

    /**
     * A link field: an `https` address or a path of the site, checked on the server with LinkTargetRule.
     */
    protected static function linkField(string $name, string $label, bool $required): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->helperText(__('filament.footer.link_help'))
            ->placeholder('https://… / /eventos')
            ->maxLength(LinkTargetRule::MAX_LENGTH)
            ->required($required)
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && self::link(['url' => $value], 'url') === null) {
                    $fail(__('filament.footer.link_invalid'));
                }
            });
    }

    /**
     * A link of the block's settings as it can be drawn; null when missing or not allowed (the stored
     * row may have been written outside the panel, so it is checked again when drawn).
     *
     * @param  array<string, mixed>  $settings
     */
    protected static function link(array $settings, string $name): ?string
    {
        return LinkTargetRule::normalize($settings[$name] ?? null, app()->environment(['local', 'testing']));
    }
}
