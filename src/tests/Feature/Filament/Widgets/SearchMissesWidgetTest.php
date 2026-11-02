<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Widgets\SearchMissesWidget;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

final class SearchMissesWidgetTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    public function test_widget_hidden_without_permission(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.view_any']));
        $this->assertFalse(SearchMissesWidget::canView());

        $this->actingAs($this->editorWithPermissions(['search.view_stats']));
        $this->assertTrue(SearchMissesWidget::canView());

        $this->actingAs(UserModel::factory()->admin()->create());
        $this->assertTrue(SearchMissesWidget::canView());
    }

    public function test_widget_lists_top_misses(): void
    {
        $this->insert('mesa de rol', 1, 7);
        $this->insert('torneo', 2, 3);
        $this->insert('antiguo', 45, 50);

        $this->actingAs($this->editorWithPermissions(['search.view_stats']));

        Livewire::test(SearchMissesWidget::class)
            ->assertSee('mesa de rol')
            ->assertSee('torneo')
            ->assertDontSee('antiguo');
    }

    public function test_editor_is_granted_view_stats_by_migration(): void
    {
        $editor = RoleModel::query()->create(['name' => 'editor', 'display_name' => 'Editor']);

        $this->runMigrationFile(database_path('migrations/2026_11_02_000004_grant_search_view_stats_to_editor.php'));

        $this->assertTrue($editor->permissions()->where('key', 'search.view_stats')->exists());
        $entry = RoleAuditEntryModel::query()->where('role_id', $editor->id)->latest('created_at')->firstOrFail();
        $this->assertSame(['permissions_added' => ['search.view_stats']], $entry->changes);
    }

    private function insert(string $query, int $daysAgo, int $count): void
    {
        DB::table('search_misses')->insert([
            'id' => (string) Str::uuid(),
            'normalized_query' => $query,
            'day' => app(TimezoneServiceInterface::class)->today()->modify("-{$daysAgo} days")->format('Y-m-d'),
            'count' => $count,
        ]);
    }
}
