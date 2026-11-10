<?php

declare(strict_types=1);

namespace Tests\Support\Filament;

use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * An event image field with the library picker, as a resource form has it.
 *
 * @property Schema $form
 */
final class MediaPickerFixturePage extends Page implements HasForms
{
    use InteractsWithForms;

    /** Rows the field accepts on top of the library rules (set by the test) */
    public static ?Closure $constrain = null;

    /** Paths always accepted, as a settings page passes them (set by the test) */
    public static ?Closure $storedPaths = null;

    /** Folder of the field (set by the test) */
    public static string $directory = 'events/2026/11';

    protected string $view = 'filament.pages.simple-settings';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $saved = null;

    public ?EventModel $record = null;

    public function mount(?string $event = null): void
    {
        $this->record = $event !== null ? EventModel::query()->findOrFail($event) : null;
        $this->form->fill($this->record?->only(['image_public_id']) ?? []);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MediaLibraryPicker::attachTo(
                    FileUpload::make('image_public_id')
                        ->label('Imagen')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png'])
                        ->disk('images')
                        ->directory(self::$directory)
                        ->maxSize(2048),
                    storedPaths: self::$storedPaths,
                    constrain: self::$constrain,
                ),
            ])
            ->model($this->record ?? EventModel::class)
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Guardar cambios')->submit('save'),
        ];
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }
}
