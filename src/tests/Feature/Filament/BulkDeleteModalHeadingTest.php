<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class BulkDeleteModalHeadingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_bulk_delete_modal_keeps_the_plural_label_in_sentence_case(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $item = MenuItemModel::factory()->create();

        Livewire::test(ListMenuItems::class)
            // The list title starts the sentence: it keeps its capital
            ->assertSee('Elementos de menú')
            ->mountTableBulkAction('delete', [$item])
            ->assertMountedActionModalSee('Borrar elementos de menú seleccionados');
    }
}
