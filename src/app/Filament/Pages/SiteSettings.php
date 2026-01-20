<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @property Form $form
 */
final class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

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

    public function mount(SettingsServiceInterface $settingsService): void
    {
        $activitiesJson = (string) $settingsService->get('about_activities', '');
        $activities = [];
        if ($activitiesJson !== '') {
            try {
                $decoded = json_decode($activitiesJson, true, 512, JSON_THROW_ON_ERROR);
                $activities = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                $activities = [];
            }
        }

        $joinStepsJson = (string) $settingsService->get('join_steps', '');
        $joinSteps = [];
        if ($joinStepsJson !== '') {
            try {
                $decoded = json_decode($joinStepsJson, true, 512, JSON_THROW_ON_ERROR);
                $joinSteps = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                $joinSteps = [];
            }
        }

        $this->form->fill([
            'location_name' => (string) $settingsService->get('location_name', ''),
            'location_address' => (string) $settingsService->get('location_address', ''),
            'location_lat' => (string) $settingsService->get('location_lat', ''),
            'location_lng' => (string) $settingsService->get('location_lng', ''),
            'location_zoom' => (string) $settingsService->get('location_zoom', '15'),
            'association_name' => (string) $settingsService->get('association_name', ''),
            'about_history' => (string) $settingsService->get('about_history', ''),
            'about_hero_image' => (string) $settingsService->get('about_hero_image', ''),
            'about_tagline' => (string) $settingsService->get('about_tagline', ''),
            'about_activities' => $activities,
            'join_steps' => $joinSteps,
            'contact_email' => (string) $settingsService->get('contact_email', ''),
            'contact_phone' => (string) $settingsService->get('contact_phone', ''),
            'contact_address' => (string) $settingsService->get('contact_address', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.settings.location.title'))
                    ->description(__('filament.settings.location.description'))
                    ->schema([
                        TextInput::make('location_name')
                            ->label(__('filament.settings.location.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('location_address')
                            ->label(__('filament.settings.location.address'))
                            ->required()
                            ->maxLength(255),
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

                Section::make(__('filament.settings.about.title'))
                    ->schema([
                        TextInput::make('association_name')
                            ->label(__('filament.settings.about.association_name'))
                            ->maxLength(255)
                            ->helperText(__('filament.settings.about.association_name_help')),

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

                        RichEditor::make('about_history')
                            ->label(__('filament.settings.about.history'))
                            ->helperText(__('filament.settings.about.history_help'))
                            ->columnSpanFull(),

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

                Section::make(__('filament.settings.contact.title'))
                    ->schema([
                        TextInput::make('contact_email')
                            ->label(__('filament.settings.contact.email'))
                            ->email(),
                        TextInput::make('contact_phone')
                            ->label(__('filament.settings.contact.phone'))
                            ->tel(),
                        Textarea::make('contact_address')
                            ->label(__('filament.settings.contact.address'))
                            ->rows(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(SettingsServiceInterface $settingsService): void
    {
        $formData = $this->form->getState();

        // Handle hero image change (delete old image if changed)
        $oldHeroImage = (string) $settingsService->get('about_hero_image', '');
        $newHeroImage = (string) ($formData['about_hero_image'] ?? '');
        if ($oldHeroImage !== '' && $oldHeroImage !== $newHeroImage) {
            Storage::disk('images')->delete($oldHeroImage);
        }

        // Serialize activities to JSON
        $activities = $formData['about_activities'] ?? [];
        $activitiesJson = is_array($activities) && count($activities) > 0
            ? json_encode(array_values($activities), JSON_THROW_ON_ERROR)
            : '';

        $joinSteps = $formData['join_steps'] ?? [];
        $joinStepsJson = is_array($joinSteps) && count($joinSteps) > 0
            ? json_encode(array_values($joinSteps), JSON_THROW_ON_ERROR)
            : '';

        $settingsService->set('location_name', (string) $formData['location_name']);
        $settingsService->set('location_address', (string) $formData['location_address']);
        $settingsService->set('location_lat', (string) $formData['location_lat']);
        $settingsService->set('location_lng', (string) $formData['location_lng']);
        $settingsService->set('location_zoom', (string) $formData['location_zoom']);
        $settingsService->set('association_name', (string) ($formData['association_name'] ?? ''));
        $settingsService->set('about_hero_image', $newHeroImage);
        $settingsService->set('about_tagline', (string) ($formData['about_tagline'] ?? ''));
        $settingsService->set('about_history', (string) ($formData['about_history'] ?? ''));
        $settingsService->set('about_activities', $activitiesJson);
        $settingsService->set('join_steps', $joinStepsJson);
        $settingsService->set('contact_email', (string) ($formData['contact_email'] ?? ''));
        $settingsService->set('contact_phone', (string) ($formData['contact_phone'] ?? ''));
        $settingsService->set('contact_address', (string) ($formData['contact_address'] ?? ''));

        Notification::make()
            ->title(__('filament.settings.location.saved'))
            ->success()
            ->send();
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
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }

    /**
     * Get location data for the view (map preview).
     */
    public function getLocationLat(): string
    {
        return (string) ($this->data['location_lat'] ?? '');
    }

    public function getLocationLng(): string
    {
        return (string) ($this->data['location_lng'] ?? '');
    }
}
