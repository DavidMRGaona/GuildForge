<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Navigation\DTOs\MenuItemDTO;
use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The links of one footer menu, as the menu editor keeps them, filtered for the person viewing.
 */
final class LinksFooterBlock extends CoreFooterBlock
{
    public function __construct(
        private readonly MenuServiceInterface $menus,
        private readonly FooterMenusInterface $footerMenus,
        private readonly MenuItemRepositoryInterface $items,
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
                ->helperText(fn (Get $get): string => $this->hasActiveLinks($this->location(['menu' => $get('menu')]))
                    ? __('filament.footer.blocks.links.menu_help')
                    : __('filament.footer.blocks.links.menu_empty'))
                ->live()
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
        $items = $this->menus->getMenu($this->location($settings), $user);

        // No link this person can see (empty menu, inactive links, another audience): only the title would be left
        if ($items === []) {
            throw NothingToDrawException::make();
        }

        return [
            'items' => array_map(static fn (MenuItemDTO $item): array => $item->toArray(), $items),
        ];
    }

    /**
     * Whether the menu has any active link for anybody; the panel warns when it has none.
     */
    private function hasActiveLinks(MenuLocation $location): bool
    {
        return $this->items->findActiveByLocationWithChildren($location) !== [];
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
