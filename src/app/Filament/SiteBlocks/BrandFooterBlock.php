<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\DTOs\Response\SiteLogoDTO;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Domain\Enums\ColorScheme;
use App\Infrastructure\Social\SocialLinkResolver;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The site's logo, name and social networks, from the site settings, with an optional short text.
 * The footer is dark in both colour schemes, so it shows the dark-scheme logo.
 */
final class BrandFooterBlock extends CoreFooterBlock
{
    public const string DEFAULT_LOGO = '/images/defaults/logo-dark.png';

    public function __construct(
        private readonly SiteBrandingServiceInterface $branding,
        private readonly SocialLinkResolver $socialLinks,
    ) {}

    public function key(): string
    {
        return 'core.brand';
    }

    public function label(): string
    {
        return __('filament.footer.blocks.brand.label');
    }

    public function settingsSchema(): array
    {
        return [
            Toggle::make('show_logo')
                ->label(__('filament.footer.blocks.brand.show_logo'))
                ->default(true),
            Toggle::make('show_name')
                ->label(__('filament.footer.blocks.brand.show_name'))
                ->default(true),
            Textarea::make('description')
                ->label(__('filament.footer.blocks.brand.description'))
                ->rows(2)
                ->maxLength(300),
            Toggle::make('show_social')
                ->label(__('filament.footer.blocks.brand.show_social'))
                ->helperText(__('filament.footer.blocks.brand.show_social_help'))
                ->default(true),
        ];
    }

    public function component(): string
    {
        return 'components/siteBlocks/BrandBlock.vue';
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        return [
            'logo' => self::enabled($settings, 'show_logo') ? $this->logo() : null,
            'name' => self::enabled($settings, 'show_name') ? $this->branding->siteName() : null,
            'description' => self::text($settings, 'description'),
            'socialLinks' => self::enabled($settings, 'show_social')
                ? array_filter($this->socialLinks->publicAddresses(), static fn (string $address): bool => $address !== '')
                : [],
        ];
    }

    private function logo(): string
    {
        $logo = $this->branding->customLogoFor(ColorScheme::Dark);

        return $logo instanceof SiteLogoDTO ? $logo->url : self::DEFAULT_LOGO;
    }
}
