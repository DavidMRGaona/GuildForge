<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Concerns\ManagesPageSettings;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @property Form $form
 */
final class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesPageSettings;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.site-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.settings.title');
    }

    public function getTitle(): string
    {
        return __('filament.settings.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string>
     */
    protected function getSettingsKeys(): array
    {
        return [
            'guild_name',
            'guild_description',
            'site_logo_light',
            'site_logo_dark',
            'theme_primary_color',
            'theme_primary_color_dark',
            'theme_secondary_color',
            'theme_secondary_color_dark',
            'theme_accent_color',
            'theme_background_color',
            'theme_background_color_dark',
            'theme_surface_color',
            'theme_surface_color_dark',
            'theme_text_color',
            'theme_text_color_dark',
            'theme_font_heading',
            'theme_font_body',
            'theme_font_size_base',
            'theme_border_radius',
            'theme_shadow_intensity',
            'theme_button_style',
            'theme_dark_mode_default',
            'theme_dark_mode_toggle_visible',
            'auth_registration_enabled',
            'auth_login_enabled',
            'auth_email_verification_required',
            'anonymized_user_name',
        ];
    }

    /**
     * @return array<string>
     */
    protected function getJsonFields(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getImageFields(): array
    {
        return ['site_logo_light', 'site_logo_dark'];
    }

    public function mount(SettingsServiceInterface $settingsService): void
    {
        $this->form->fill($this->loadSettings($settingsService));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make(__('filament.settings.tabs.general'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                TextInput::make('guild_name')
                                    ->label(__('filament.settings.about.guild_name'))
                                    ->maxLength(255)
                                    ->helperText(__('filament.settings.about.guild_name_help')),

                                TextInput::make('guild_description')
                                    ->label(__('filament.settings.general.description'))
                                    ->maxLength(500)
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('filament.settings.tabs.logos'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                FileUpload::make('site_logo_light')
                                    ->label(__('filament.settings.general.logo_light'))
                                    ->helperText(__('filament.settings.general.logo_light_help'))
                                    ->image()
                                    ->disk('images')
                                    ->directory('branding')
                                    ->getUploadedFileNameForStorageUsing(
                                        fn (TemporaryUploadedFile $file): string => 'logo-light-'.Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                                    )
                                    ->maxSize(1024)
                                    ->nullable()
                                    ->columnSpanFull(),

                                FileUpload::make('site_logo_dark')
                                    ->label(__('filament.settings.general.logo_dark'))
                                    ->helperText(__('filament.settings.general.logo_dark_help'))
                                    ->image()
                                    ->disk('images')
                                    ->directory('branding')
                                    ->getUploadedFileNameForStorageUsing(
                                        fn (TemporaryUploadedFile $file): string => 'logo-dark-'.Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                                    )
                                    ->maxSize(1024)
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make(__('filament.settings.tabs.colors'))
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                Section::make(__('filament.settings.colors.primary_section'))
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                ColorPicker::make('theme_primary_color')
                                                    ->label(__('filament.settings.colors.primary_color'))
                                                    ->default('#D97706'),

                                                ColorPicker::make('theme_primary_color_dark')
                                                    ->label(__('filament.settings.colors.primary_color_dark'))
                                                    ->default('#F59E0B'),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                ColorPicker::make('theme_secondary_color')
                                                    ->label(__('filament.settings.colors.secondary_color'))
                                                    ->default('#57534E'),

                                                ColorPicker::make('theme_secondary_color_dark')
                                                    ->label(__('filament.settings.colors.secondary_color_dark'))
                                                    ->default('#A8A29E'),
                                            ]),

                                        ColorPicker::make('theme_accent_color')
                                            ->label(__('filament.settings.colors.accent_color'))
                                            ->default('#D97706'),
                                    ]),

                                Section::make(__('filament.settings.colors.background_section'))
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                ColorPicker::make('theme_background_color')
                                                    ->label(__('filament.settings.colors.background_color'))
                                                    ->default('#FAFAF9'),

                                                ColorPicker::make('theme_background_color_dark')
                                                    ->label(__('filament.settings.colors.background_color_dark'))
                                                    ->default('#1C1917'),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                ColorPicker::make('theme_surface_color')
                                                    ->label(__('filament.settings.colors.surface_color'))
                                                    ->default('#FFFFFF'),

                                                ColorPicker::make('theme_surface_color_dark')
                                                    ->label(__('filament.settings.colors.surface_color_dark'))
                                                    ->default('#292524'),
                                            ]),
                                    ]),

                                Section::make(__('filament.settings.colors.text_section'))
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                ColorPicker::make('theme_text_color')
                                                    ->label(__('filament.settings.colors.text_color'))
                                                    ->default('#1C1917'),

                                                ColorPicker::make('theme_text_color_dark')
                                                    ->label(__('filament.settings.colors.text_color_dark'))
                                                    ->default('#F5F5F4'),
                                            ]),
                                    ]),
                            ]),

                        Tab::make(__('filament.settings.tabs.typography'))
                            ->icon('heroicon-o-language')
                            ->schema([
                                Select::make('theme_font_heading')
                                    ->label(__('filament.settings.typography.font_heading'))
                                    ->options([
                                        'Inter' => 'Inter',
                                        'Poppins' => 'Poppins',
                                        'Montserrat' => 'Montserrat',
                                        'Roboto' => 'Roboto',
                                        'Open Sans' => 'Open Sans',
                                        'system-ui' => 'System Default',
                                    ])
                                    ->default('Inter'),

                                Select::make('theme_font_body')
                                    ->label(__('filament.settings.typography.font_body'))
                                    ->options([
                                        'Inter' => 'Inter',
                                        'Poppins' => 'Poppins',
                                        'Montserrat' => 'Montserrat',
                                        'Roboto' => 'Roboto',
                                        'Open Sans' => 'Open Sans',
                                        'system-ui' => 'System Default',
                                    ])
                                    ->default('Inter'),

                                Select::make('theme_font_size_base')
                                    ->label(__('filament.settings.typography.font_size_base'))
                                    ->options([
                                        'small' => __('filament.settings.typography.font_size_small'),
                                        'normal' => __('filament.settings.typography.font_size_normal'),
                                        'large' => __('filament.settings.typography.font_size_large'),
                                    ])
                                    ->default('normal'),
                            ]),

                        Tab::make(__('filament.settings.tabs.appearance'))
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Select::make('theme_border_radius')
                                    ->label(__('filament.settings.appearance.border_radius'))
                                    ->options([
                                        'none' => __('filament.settings.appearance.border_radius_none'),
                                        'subtle' => __('filament.settings.appearance.border_radius_subtle'),
                                        'medium' => __('filament.settings.appearance.border_radius_medium'),
                                        'large' => __('filament.settings.appearance.border_radius_large'),
                                        'rounded' => __('filament.settings.appearance.border_radius_rounded'),
                                    ])
                                    ->default('medium'),

                                Select::make('theme_shadow_intensity')
                                    ->label(__('filament.settings.appearance.shadow_intensity'))
                                    ->options([
                                        'none' => __('filament.settings.appearance.shadow_none'),
                                        'subtle' => __('filament.settings.appearance.shadow_subtle'),
                                        'medium' => __('filament.settings.appearance.shadow_medium'),
                                        'pronounced' => __('filament.settings.appearance.shadow_pronounced'),
                                    ])
                                    ->default('medium'),

                                Select::make('theme_button_style')
                                    ->label(__('filament.settings.appearance.button_style'))
                                    ->options([
                                        'solid' => __('filament.settings.appearance.button_solid'),
                                        'outline' => __('filament.settings.appearance.button_outline'),
                                        'ghost' => __('filament.settings.appearance.button_ghost'),
                                    ])
                                    ->default('solid'),

                                Toggle::make('theme_dark_mode_default')
                                    ->label(__('filament.settings.appearance.dark_mode_default'))
                                    ->helperText(__('filament.settings.appearance.dark_mode_default_help'))
                                    ->default(false),

                                Toggle::make('theme_dark_mode_toggle_visible')
                                    ->label(__('filament.settings.appearance.dark_mode_toggle_visible'))
                                    ->helperText(__('filament.settings.appearance.dark_mode_toggle_visible_help'))
                                    ->default(true),
                            ]),

                        Tab::make(__('filament.settings.tabs.authentication'))
                            ->icon('heroicon-o-key')
                            ->schema([
                                Section::make(__('filament.settings.auth.section_public'))
                                    ->description(__('filament.settings.auth.section_public_description'))
                                    ->schema([
                                        Toggle::make('auth_registration_enabled')
                                            ->label(__('filament.settings.auth.registration_enabled'))
                                            ->helperText(__('filament.settings.auth.registration_enabled_help'))
                                            ->default(true),

                                        Toggle::make('auth_login_enabled')
                                            ->label(__('filament.settings.auth.login_enabled'))
                                            ->helperText(__('filament.settings.auth.login_enabled_help'))
                                            ->default(true),
                                    ]),

                                Section::make(__('filament.settings.auth.section_security'))
                                    ->description(__('filament.settings.auth.section_security_description'))
                                    ->schema([
                                        Toggle::make('auth_email_verification_required')
                                            ->label(__('filament.settings.auth.email_verification_required'))
                                            ->helperText(__('filament.settings.auth.email_verification_required_help'))
                                            ->default(false),
                                    ]),

                                Section::make(__('filament.settings.auth.section_gdpr'))
                                    ->description(__('filament.settings.auth.section_gdpr_description'))
                                    ->schema([
                                        TextInput::make('anonymized_user_name')
                                            ->label(__('filament.settings.auth.anonymized_user_name'))
                                            ->helperText(__('filament.settings.auth.anonymized_user_name_help'))
                                            ->default('Anónimo')
                                            ->maxLength(100),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(SettingsServiceInterface $settingsService): void
    {
        $formData = $this->form->getState();
        $this->saveSettings($settingsService, $formData);

        Notification::make()
            ->title(__('filament.settings.location.saved'))
            ->success()
            ->send();
    }
}
