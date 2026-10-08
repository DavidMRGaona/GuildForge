<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Filament\SimpleSettingsFixturePage;
use Tests\TestCase;

/**
 * "filament.pages.simple-settings" is a view modules render: it must show the form
 * and its actions, and submit to save().
 */
final class SimpleSettingsViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_view_renders_the_form_and_its_actions_and_saves(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SimpleSettingsFixturePage::class)
            ->assertOk()
            ->assertSee('Nombre del club')
            ->assertSee('Guardar cambios')
            ->assertSeeHtml('wire:submit="save"')
            ->fillForm(['club_name' => 'Gremio del dragón'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', 'Gremio del dragón');
    }
}
