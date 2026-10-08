<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\ManagesPageSettings;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @property \Filament\Schemas\Schema $form
 */
final class SiteSettings extends Page implements HasForms
{
    use ChecksPermissions;
    use InteractsWithForms;
    use ManagesPageSettings;

    /**
     * Predefined theme color palettes with WCAG AA contrast compliance.
     *
     * @var array<string, array{primary: string, accent: string}>
     */
    private const array THEME_PRESETS = [
        'clasico-dorado' => ['primary' => '#D97706', 'accent' => '#0EA5E9'],
        'llamas-dragon' => ['primary' => '#DC2626', 'accent' => '#F59E0B'],
        'bosque-elfico' => ['primary' => '#059669', 'accent' => '#84CC16'],
        'profundidades-oceanicas' => ['primary' => '#0284C7', 'accent' => '#06B6D4'],
        'purpura-real' => ['primary' => '#9333EA', 'accent' => '#EC4899'],
        'reino-sombrio' => ['primary' => '#475569', 'accent' => '#8B5CF6'],
        'acero-forja' => ['primary' => '#0F766E', 'accent' => '#FB923C'],
    ];

    /**
     * @var array<string>
     */
    private const array GENERAL_SETTINGS_KEYS = [
        'guild_name',
        'guild_description',
        'site_logo_light',
        'site_logo_dark',
        'site_logo_email',
        'site_favicon_light',
        'site_favicon_dark',
        'theme_primary_base_color',
        'theme_accent_base_color',
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

    /**
     * @var array<string>
     */
    private const array MAINTENANCE_SETTINGS_KEYS = [
        'maintenance_enabled',
        'maintenance_message',
    ];

    /**
     * Filament 4+ matches ?tab= against the tab key or its custom id
     */
    private const string MAINTENANCE_TAB = 'maintenance';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.site-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.settings.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.admin');
    }

    public function getTitle(): string
    {
        return __('filament.settings.title');
    }

    public static function canAccess(): bool
    {
        return self::userCan('settings.manage') || self::userCan('settings.maintenance');
    }

    public static function getMaintenanceTabUrl(): string
    {
        return self::getUrl(['tab' => self::MAINTENANCE_TAB]);
    }

    /**
     * Only the keys the user may edit are loaded and saved, so saving never blanks
     * (or deletes the images of) settings from tabs the user cannot see.
     *
     * @return array<string>
     */
    protected function getSettingsKeys(): array
    {
        $keys = [];

        if (self::userCan('settings.manage')) {
            $keys = self::GENERAL_SETTINGS_KEYS;
        }

        if (self::userCan('settings.maintenance')) {
            $keys = [...$keys, ...self::MAINTENANCE_SETTINGS_KEYS];
        }

        return $keys;
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
        return ['site_logo_light', 'site_logo_dark', 'site_logo_email', 'site_favicon_light', 'site_favicon_dark'];
    }

    /**
     * @return array<string>
     */
    protected function getEncryptedFields(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultSettings(): array
    {
        return [
            'guild_name' => '',
            'guild_description' => '',
            'site_logo_light' => '',
            'site_logo_dark' => '',
            'site_logo_email' => '',
            'site_favicon_light' => '',
            'site_favicon_dark' => '',
            'theme_primary_base_color' => '#D97706',
            'theme_accent_base_color' => '#0EA5E9',
            'theme_font_heading' => 'Inter',
            'theme_font_body' => 'Inter',
            'theme_font_size_base' => 'normal',
            'theme_border_radius' => 'medium',
            'theme_shadow_intensity' => 'medium',
            'theme_button_style' => 'solid',
            'theme_dark_mode_default' => false,
            'theme_dark_mode_toggle_visible' => true,
            'auth_registration_enabled' => true,
            'auth_login_enabled' => true,
            'auth_email_verification_required' => false,
            'anonymized_user_name' => 'Anónimo',
            'maintenance_enabled' => false,
            'maintenance_message' => '',
        ];
    }

    public function mount(SettingsServiceInterface $settingsService): void
    {
        $this->form->fill($this->loadSettings($settingsService));
    }

    public function form(Schema $schema): Schema
    {
        $canManage = self::userCan('settings.manage');

        return $schema
            ->components([
                Tabs::make('Settings')
                    ->id('settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('filament.settings.tabs.general'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->visible($canManage)
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
                            ->visible($canManage)
                            ->schema([
                                Section::make(__('filament.settings.branding.logos_section'))
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

                                        FileUpload::make('site_logo_email')
                                            ->label(__('filament.settings.general.logo_email'))
                                            ->helperText(__('filament.settings.general.logo_email_help'))
                                            ->image()
                                            ->disk('images')
                                            ->directory('branding')
                                            ->getUploadedFileNameForStorageUsing(
                                                fn (TemporaryUploadedFile $file): string => 'logo-email-'.Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                                            )
                                            ->maxSize(1024)
                                            ->nullable()
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('filament.settings.branding.favicons_section'))
                                    ->description(__('filament.settings.branding.favicons_description'))
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                FileUpload::make('site_favicon_light')
                                                    ->label(__('filament.settings.branding.favicon_light'))
                                                    ->helperText(__('filament.settings.branding.favicon_light_help'))
                                                    ->image()
                                                    ->disk('images')
                                                    ->directory('branding')
                                                    ->getUploadedFileNameForStorageUsing(
                                                        fn (TemporaryUploadedFile $file): string => 'favicon-light-'.Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                                                    )
                                                    ->maxSize(512)
                                                    ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'])
                                                    ->nullable(),

                                                FileUpload::make('site_favicon_dark')
                                                    ->label(__('filament.settings.branding.favicon_dark'))
                                                    ->helperText(__('filament.settings.branding.favicon_dark_help'))
                                                    ->image()
                                                    ->disk('images')
                                                    ->directory('branding')
                                                    ->getUploadedFileNameForStorageUsing(
                                                        fn (TemporaryUploadedFile $file): string => 'favicon-dark-'.Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                                                    )
                                                    ->maxSize(512)
                                                    ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'])
                                                    ->nullable(),
                                            ]),
                                    ]),
                            ]),

                        Tab::make(__('filament.settings.tabs.colors'))
                            ->icon('heroicon-o-swatch')
                            ->visible($canManage)
                            ->schema([
                                Section::make(__('filament.settings.colors.brand_section'))
                                    ->description(__('filament.settings.colors.brand_description'))
                                    ->schema([
                                        Select::make('theme_preset')
                                            ->label(__('filament.settings.colors.preset'))
                                            ->helperText(__('filament.settings.colors.preset_help'))
                                            ->options([
                                                'clasico-dorado' => __('filament.settings.colors.presets.clasico_dorado'),
                                                'llamas-dragon' => __('filament.settings.colors.presets.llamas_dragon'),
                                                'bosque-elfico' => __('filament.settings.colors.presets.bosque_elfico'),
                                                'profundidades-oceanicas' => __('filament.settings.colors.presets.profundidades_oceanicas'),
                                                'purpura-real' => __('filament.settings.colors.presets.purpura_real'),
                                                'reino-sombrio' => __('filament.settings.colors.presets.reino_sombrio'),
                                                'acero-forja' => __('filament.settings.colors.presets.acero_forja'),
                                            ])
                                            ->placeholder(__('filament.settings.colors.preset_placeholder'))
                                            ->native(false)
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                if ($state !== null && isset(self::THEME_PRESETS[$state])) {
                                                    $preset = self::THEME_PRESETS[$state];
                                                    $set('theme_primary_base_color', $preset['primary']);
                                                    $set('theme_accent_base_color', $preset['accent']);
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                ColorPicker::make('theme_primary_base_color')
                                                    ->label(__('filament.settings.colors.primary_base'))
                                                    ->helperText(__('filament.settings.colors.primary_base_help'))
                                                    ->default('#D97706')
                                                    ->live(),

                                                ColorPicker::make('theme_accent_base_color')
                                                    ->label(__('filament.settings.colors.accent_base'))
                                                    ->helperText(__('filament.settings.colors.accent_base_help'))
                                                    ->default('#0EA5E9')
                                                    ->live(),
                                            ]),
                                    ]),

                                Section::make(__('filament.settings.colors.preview_section'))
                                    ->description(__('filament.settings.colors.preview_description'))
                                    ->schema([
                                        View::make('filament.components.color-palette-preview'),
                                    ]),
                            ]),

                        Tab::make(__('filament.settings.tabs.typography'))
                            ->icon('heroicon-o-language')
                            ->visible($canManage)
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
                            ->visible($canManage)
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
                            ->visible($canManage)
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

                        Tab::make(__('filament.settings.tabs.maintenance'))
                            ->id('maintenance')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->visible(self::userCan('settings.maintenance'))
                            ->schema([
                                Section::make(__('filament.settings.maintenance.section'))
                                    ->description(__('filament.settings.maintenance.section_description'))
                                    ->schema([
                                        Toggle::make('maintenance_enabled')
                                            ->label(__('filament.settings.maintenance.enabled'))
                                            ->helperText(__('filament.settings.maintenance.enabled_help'))
                                            ->default(false),

                                        Textarea::make('maintenance_message')
                                            ->label(__('filament.settings.maintenance.message'))
                                            ->helperText(__('filament.settings.maintenance.message_help'))
                                            ->rows(3)
                                            ->maxLength(500),
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
