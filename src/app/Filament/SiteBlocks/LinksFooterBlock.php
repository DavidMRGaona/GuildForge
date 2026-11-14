<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Navigation\DTOs\MenuItemDTO;
use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Domain\Navigation\Enums\MenuLocation;
use Filament\Forms\Components\Select;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The links of one footer menu, as the menu editor keeps them, filtered for the person viewing.
 */
final class LinksFooterBlock extends CoreFooterBlock
{
    public function __construct(
        private readonly MenuServiceInterface $menus,
        private readonly FooterMenusInterface $footerMenus,
    ) {}

    public function key(): string
    {
        return 'core.links';
    }

    public function label(): string
    {
        return __('filament.footer.blocks.links.label');
    }

    public function settingsSchema(): array
    {
        return [
            Select::make('menu')
                ->label(__('filament.footer.blocks.links.menu'))
                ->helperText(__('filament.footer.blocks.links.menu_help'))
                ->options(fn (): array => $this->footerMenus->options())
                ->default(MenuLocation::Footer->value)
                ->required()
                ->native(false),
        ];
    }

    public function component(): string
    {
        return 'components/siteBlocks/LinksBlock.vue';
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        return [
            'items' => array_map(
                static fn (MenuItemDTO $item): array => $item->toArray(),
                $this->menus->getMenu($this->location($settings), $user),
            ),
        ];
    }

    /**
     * The chosen footer menu; anything else (missing, the header, unknown) is the first one.
     *
     * @param  array<string, mixed>  $settings
     */
    private function location(array $settings): MenuLocation
    {
        $menu = $settings['menu'] ?? null;
        $location = is_string($menu) ? MenuLocation::tryFrom($menu) : null;

        return $location instanceof MenuLocation && $location->isFooter() ? $location : MenuLocation::Footer;
    }
}
