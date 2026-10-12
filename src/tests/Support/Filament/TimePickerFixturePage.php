<?php

declare(strict_types=1);

namespace Tests\Support\Filament;

use App\Filament\Forms\Components\TimePicker;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * A form with the core's own TimePicker, as module forms use it (game-tables time slots).
 *
 * @property Schema $form
 */
final class TimePickerFixturePage extends Page implements HasForms
{
    use InteractsWithForms;

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

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TimePicker::make('starts_at')->label('Hora de inicio')->required(),
                TimePicker::make('ends_at')->label('Hora de fin'),
                TimePicker::make('closes_at')->label('Hora de cierre')->seconds(),
            ])
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
