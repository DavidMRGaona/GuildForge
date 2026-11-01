<?php

declare(strict_types=1);

namespace Tests\Support\Filament;

use App\Filament\Forms\Components\EndSurveyField;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * A form filled with explicit data and no record, as a module's own page does it (the tournament
 * tab of an event that has no tournament yet): Filament applies no defaults then.
 *
 * @property Schema $form
 */
final class EndSurveyFieldFixturePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.simple-settings';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['name' => 'Torneo de ejemplo']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name'),
                EndSurveyField::forSubject('example-thing'),
            ])
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
