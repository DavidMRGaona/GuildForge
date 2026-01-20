<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Concerns\ManagesPageSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
final class AboutPageSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesPageSettings;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Páginas';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.settings.about-page-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.pages.about.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.pages');
    }

    public function getTitle(): string
    {
        return __('filament.pages.about.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(SettingsServiceInterface $settingsService): void
    {
        $this->form->fill($this->loadSettings($settingsService));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make(__('filament.pages.about.tabs.hero'))
                            ->schema([
                                FileUpload::make('about_hero_image')
                                    ->label(__('filament.settings.about.hero_image'))
                                    ->helperText(__('filament.settings.about.hero_image_help'))
                                    ->image()
                                    ->disk('images')
                                    ->directory('about')
                                    ->getUploadedFileNameForStorageUsing(
                                        fn (TemporaryUploadedFile $file): string => Str::uuid()->toString() . '.' . $file->getClientOriginalExtension()
                                    )
                                    ->maxSize(2048)
                                    ->nullable()
                                    ->columnSpanFull(),

                                TextInput::make('about_tagline')
                                    ->label(__('filament.settings.about.tagline'))
                                    ->helperText(__('filament.settings.about.tagline_help'))
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('filament.pages.about.tabs.content'))
                            ->schema([
                                Repeater::make('about_activities')
                                    ->label(__('filament.settings.about.activities'))
                                    ->helperText(__('filament.settings.about.activities_help'))
                                    ->schema([
                                        Select::make('icon')
                                            ->label(__('filament.settings.about.activity_icon'))
                                            ->options(self::getActivityIconOptions())
                                            ->required()
                                            ->native(false),
                                        TextInput::make('title')
                                            ->label(__('filament.settings.about.activity_title'))
                                            ->required()
                                            ->maxLength(100),
                                        Textarea::make('description')
                                            ->label(__('filament.settings.about.activity_description'))
                                            ->required()
                                            ->rows(2)
                                            ->maxLength(500),
                                    ])
                                    ->reorderable()
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->maxItems(8)
                                    ->columnSpanFull(),

                                RichEditor::make('about_history')
                                    ->label(__('filament.settings.about.history'))
                                    ->helperText(__('filament.settings.about.history_help'))
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('filament.pages.about.tabs.join'))
                            ->schema([
                                Repeater::make('join_steps')
                                    ->label(__('filament.settings.about.join_steps'))
                                    ->helperText(__('filament.settings.about.join_steps_help'))
                                    ->schema([
                                        TextInput::make('title')
                                            ->label(__('filament.settings.about.join_step_title'))
                                            ->required()
                                            ->maxLength(100),
                                        Textarea::make('description')
                                            ->label(__('filament.settings.about.join_step_description'))
                                            ->rows(2)
                                            ->maxLength(500),
                                    ])
                                    ->reorderable()
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->maxItems(6)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make(__('filament.pages.about.tabs.location'))
                            ->schema([
                                TextInput::make('location_name')
                                    ->label(__('filament.settings.location.name'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('location_address')
                                    ->label(__('filament.settings.location.address'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('location_lat')
                                    ->label(__('filament.settings.location.lat'))
                                    ->required()
                                    ->numeric()
                                    ->rules(['required', 'numeric', 'between:-90,90']),
                                TextInput::make('location_lng')
                                    ->label(__('filament.settings.location.lng'))
                                    ->required()
                                    ->numeric()
                                    ->rules(['required', 'numeric', 'between:-180,180']),
                                TextInput::make('location_zoom')
                                    ->label(__('filament.settings.location.zoom'))
                                    ->required()
                                    ->numeric()
                                    ->rules(['required', 'integer', 'between:1,18'])
                                    ->default('15'),
                            ])->columns(2),

                        Tabs\Tab::make(__('filament.pages.about.tabs.contact'))
                            ->schema([
                                TextInput::make('contact_email')
                                    ->label(__('filament.settings.contact.email'))
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('contact_phone')
                                    ->label(__('filament.settings.contact.phone'))
                                    ->tel()
                                    ->maxLength(255),
                                Textarea::make('contact_address')
                                    ->label(__('filament.settings.contact.address'))
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tabs\Tab::make(__('filament.pages.about.tabs.social'))
                            ->schema([
                                TextInput::make('social_facebook')
                                    ->label(__('filament.settings.social.facebook'))
                                    ->prefix('https://')
                                    ->placeholder('www.facebook.com/tu-pagina')
                                    ->maxLength(255),
                                TextInput::make('social_instagram')
                                    ->label(__('filament.settings.social.instagram'))
                                    ->prefix('https://')
                                    ->placeholder('www.instagram.com/tu-usuario')
                                    ->maxLength(255),
                                TextInput::make('social_twitter')
                                    ->label(__('filament.settings.social.twitter'))
                                    ->prefix('https://')
                                    ->placeholder('x.com/tu-usuario')
                                    ->maxLength(255),
                                TextInput::make('social_discord')
                                    ->label(__('filament.settings.social.discord'))
                                    ->prefix('https://')
                                    ->placeholder('discord.gg/tu-servidor')
                                    ->maxLength(255),
                                TextInput::make('social_tiktok')
                                    ->label(__('filament.settings.social.tiktok'))
                                    ->prefix('https://')
                                    ->placeholder('www.tiktok.com/@tu-usuario')
                                    ->maxLength(255),
                            ])->columns(2),
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
            ->title(__('filament.pages.about.saved'))
            ->success()
            ->send();
    }

    /**
     * Get the list of setting keys this page manages.
     *
     * @return array<string>
     */
    protected function getSettingsKeys(): array
    {
        return [
            'about_hero_image',
            'about_tagline',
            'about_activities',
            'about_history',
            'join_steps',
            'location_name',
            'location_address',
            'location_lat',
            'location_lng',
            'location_zoom',
            'contact_email',
            'contact_phone',
            'contact_address',
            'social_facebook',
            'social_instagram',
            'social_twitter',
            'social_discord',
            'social_tiktok',
        ];
    }

    /**
     * Get the list of field names that need JSON encoding/decoding.
     *
     * @return array<string>
     */
    protected function getJsonFields(): array
    {
        return ['about_activities', 'join_steps'];
    }

    /**
     * Get the list of field names that are image uploads.
     *
     * @return array<string>
     */
    protected function getImageFields(): array
    {
        return ['about_hero_image'];
    }

    /**
     * Get available activity icon options.
     *
     * @return array<string, string>
     */
    private static function getActivityIconOptions(): array
    {
        return [
            'dice' => __('filament.settings.about.icons.dice'),
            'sword' => __('filament.settings.about.icons.sword'),
            'book' => __('filament.settings.about.icons.book'),
            'users' => __('filament.settings.about.icons.users'),
            'calendar' => __('filament.settings.about.icons.calendar'),
            'map' => __('filament.settings.about.icons.map'),
            'trophy' => __('filament.settings.about.icons.trophy'),
            'puzzle' => __('filament.settings.about.icons.puzzle'),
            'sparkles' => __('filament.settings.about.icons.sparkles'),
            'heart' => __('filament.settings.about.icons.heart'),
        ];
    }

    /**
     * Get location latitude for map preview.
     */
    public function getLocationLat(): string
    {
        return (string) ($this->data['location_lat'] ?? '');
    }

    /**
     * Get location longitude for map preview.
     */
    public function getLocationLng(): string
    {
        return (string) ($this->data['location_lng'] ?? '');
    }
}
