<?php

declare(strict_types=1);

namespace Tests\Support\Filament;

use App\Filament\Impersonation\ReadOnlyActions;
use App\Filament\Impersonation\ReadsOnly;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Stands in for a module's panel page: its own header actions, a settings form saved by a
 * public method and a method that only changes what the page shows. Nothing on it knows about
 * impersonation, except what it marks as only reading.
 *
 * @property Schema $form
 */
final class ImpersonationFixturePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.simple-settings';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public ?string $saved = null;

    public bool $ran = false;

    public ?string $note = null;

    public ?string $section = null;

    public bool $peeked = false;

    public function mount(): void
    {
        $this->form->fill(['club_name' => 'Gremio']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('club_name')->label('Nombre del club')->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->saved = (string) $this->form->getState()['club_name'];
    }

    #[ReadsOnly]
    public function showSection(string $section): void
    {
        $this->section = $section;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Runs on click, with no modal in between
            Action::make('run')->action(function (): void {
                $this->ran = true;
            }),
            Action::make('confirmRun')->requiresConfirmation()->action(function (): void {
                $this->ran = true;
            }),
            Action::make('addNote')
                ->schema([TextInput::make('note')->required()])
                ->action(function (array $data): void {
                    $this->note = (string) $data['note'];
                }),
            ReadOnlyActions::allow(Action::make('peek')->action(function (): void {
                $this->peeked = true;
            })),
        ];
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
}
