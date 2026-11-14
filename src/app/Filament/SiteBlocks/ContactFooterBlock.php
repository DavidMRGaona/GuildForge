<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Services\SettingsServiceInterface;
use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The contact details of «Nosotros» (email, phone, address) and a link to its contact form.
 */
final class ContactFooterBlock extends CoreFooterBlock
{
    /** The contact form lives on the «Nosotros» page */
    public const string FORM_ANCHOR = '#contact-section';

    public function __construct(
        private readonly SettingsServiceInterface $settings,
    ) {}

    public function key(): string
    {
        return 'core.contact';
    }

    public function label(): string
    {
        return __('filament.footer.blocks.contact.label');
    }

    public function settingsSchema(): array
    {
        return [
            Toggle::make('show_email')
                ->label(__('filament.footer.blocks.contact.show_email'))
                ->default(true),
            Toggle::make('show_phone')
                ->label(__('filament.footer.blocks.contact.show_phone'))
                ->default(true),
            Toggle::make('show_address')
                ->label(__('filament.footer.blocks.contact.show_address'))
                ->default(true),
            Toggle::make('show_link')
                ->label(__('filament.footer.blocks.contact.show_link'))
                ->default(true),
        ];
    }

    public function component(): string
    {
        return 'components/siteBlocks/ContactBlock.vue';
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        return [
            'email' => self::enabled($settings, 'show_email') ? $this->setting('contact_email') : null,
            'phone' => self::enabled($settings, 'show_phone') ? $this->setting('contact_phone') : null,
            'address' => self::enabled($settings, 'show_address') ? $this->setting('contact_address') : null,
            'contactUrl' => self::enabled($settings, 'show_link') ? route('about', [], false).self::FORM_ANCHOR : null,
        ];
    }

    private function setting(string $key): ?string
    {
        return self::text(['value' => $this->settings->get($key)], 'value');
    }
}
