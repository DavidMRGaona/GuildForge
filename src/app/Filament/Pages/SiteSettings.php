<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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
        $this->form->fill([
            'location_name' => (string) $settingsService->get('location_name', ''),
            'location_address' => (string) $settingsService->get('location_address', ''),
            'location_lat' => (string) $settingsService->get('location_lat', ''),
            'location_lng' => (string) $settingsService->get('location_lng', ''),
            'location_zoom' => (string) $settingsService->get('location_zoom', '15'),
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
            ])
            ->statePath('data');
    }

    public function save(SettingsServiceInterface $settingsService): void
    {
        $formData = $this->form->getState();

        $settingsService->set('location_name', (string) $formData['location_name']);
        $settingsService->set('location_address', (string) $formData['location_address']);
        $settingsService->set('location_lat', (string) $formData['location_lat']);
        $settingsService->set('location_lng', (string) $formData['location_lng']);
        $settingsService->set('location_zoom', (string) $formData['location_zoom']);

        Notification::make()
            ->title(__('filament.settings.location.saved'))
            ->success()
            ->send();
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
