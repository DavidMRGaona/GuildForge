<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Domain\SiteBlocks\LinkTargetRule;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * A button that links to an `https` address or a path of the site (core#698), main or secondary.
 * A stored link that is not allowed (the row may be written outside the panel) is never drawn: the
 * block throws, which the renderer turns into «not drawn».
 */
final class ButtonFooterBlock extends CoreFooterBlock
{
    public const string STYLE_PRIMARY = 'primary';

    public const string STYLE_SECONDARY = 'secondary';

    public const int MAX_LABEL_LENGTH = 60;

    public function key(): string
    {
        return 'core.button';
    }

    public function label(): string
    {
        return __('filament.footer.blocks.button.label');
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('label')
                ->label(__('filament.footer.blocks.button.text'))
                ->required()
                ->maxLength(self::MAX_LABEL_LENGTH),
            self::linkField('url', __('filament.footer.blocks.button.url'), required: true),
            ToggleButtons::make('style')
                ->label(__('filament.footer.blocks.button.style'))
                ->options([
                    self::STYLE_PRIMARY => __('filament.footer.blocks.button.styles.primary'),
                    self::STYLE_SECONDARY => __('filament.footer.blocks.button.styles.secondary'),
                ])
                ->default(self::STYLE_PRIMARY)
                ->inline()
                ->required(),
        ];
    }

    public function component(): string
    {
        return 'components/siteBlocks/ButtonBlock.vue';
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        $label = self::text($settings, 'label');
        $url = self::link($settings, 'url');

        if ($label === null || $url === null) {
            throw new InvalidArgumentException('The button has no text or its link is not allowed.');
        }

        return [
            'label' => mb_substr($label, 0, self::MAX_LABEL_LENGTH),
            'url' => $url,
            'external' => LinkTargetRule::isExternal($url),
            'variant' => ($settings['style'] ?? null) === self::STYLE_SECONDARY ? self::STYLE_SECONDARY : self::STYLE_PRIMARY,
        ];
    }
}
