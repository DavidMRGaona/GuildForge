<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Navigation\DTOs\MenuItemDTO;
use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException;
use App\Domain\Navigation\Enums\MenuLocation;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The links of one footer menu, as the menu editor keeps them, filtered for the person viewing.
 */
final class LinksFooterBlock extends CoreFooterBlock
{
    /** @var array<string, bool> Whether a menu has links for a visitor, per location: the form asks on every render */
    private array $visibleForVisitors = [];

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
                ->helperText(fn (Get $get): string => $this->hasLinksForVisitors($this->location(['menu' => $get('menu')]))
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
     * Whether a visitor without an account sees any link in the menu, as the site resolves it (inactive links,
     * links of a disabled module and other audiences left out); the panel warns when none. Remembered per menu.
     */
    private function hasLinksForVisitors(MenuLocation $location): bool
    {
        return $this->visibleForVisitors[$location->value] ??= $this->menus->getMenu($location, null) !== [];
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
