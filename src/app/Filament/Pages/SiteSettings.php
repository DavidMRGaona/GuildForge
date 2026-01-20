<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Concerns\ManagesPageSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
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
        return ['association_name', 'site_logo'];
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
        return ['site_logo'];
    }

    public function mount(SettingsServiceInterface $settingsService): void
    {
        $this->form->fill($this->loadSettings($settingsService));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.settings.general.title'))
                    ->description(__('filament.settings.general.description'))
                    ->schema([
                        FileUpload::make('site_logo')
                            ->label(__('filament.settings.general.logo'))
                            ->helperText(__('filament.settings.general.logo_help'))
                            ->image()
                            ->disk('images')
                            ->directory('branding')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => 'logo-' . Str::uuid()->toString() . '.' . $file->getClientOriginalExtension()
                            )
                            ->maxSize(1024)
                            ->nullable()
                            ->columnSpanFull(),

                        TextInput::make('association_name')
                            ->label(__('filament.settings.about.association_name'))
                            ->maxLength(255)
                            ->helperText(__('filament.settings.about.association_name_help')),
                    ]),
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
