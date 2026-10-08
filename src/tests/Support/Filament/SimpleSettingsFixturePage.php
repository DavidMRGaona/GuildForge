<?php

declare(strict_types=1);

namespace Tests\Support\Filament;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Stands in for the module settings pages that render the core's
 * "filament.pages.simple-settings" view (game-tables, memberships, venue-bookings).
 *
 * @property Form $form
 */
final class SimpleSettingsFixturePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.simple-settings';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public ?string $saved = null;

    public function mount(): void
    {
        $this->form->fill(['club_name' => 'Gremio']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('club_name')->label('Nombre del club')->required(),
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
        $this->saved = (string) $this->form->getState()['club_name'];
    }
}
