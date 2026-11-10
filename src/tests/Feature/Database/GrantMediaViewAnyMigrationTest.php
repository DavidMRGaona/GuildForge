<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

/**
 * Existing sites give the editor role the media library to look at, never the right to delete from it.
 */
final class GrantMediaViewAnyMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    private const string MIGRATION = 'migrations/2026_11_10_100004_grant_media_view_any_to_editor.php';

    public function test_editor_gets_view_any_but_not_delete(): void
    {
        $editor = RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);

        $this->runMigrationFile(database_path(self::MIGRATION));

        $this->assertTrue($editor->permissions()->where('key', 'media.view_any')->exists());
        $this->assertFalse($editor->permissions()->where('key', 'media.delete')->exists());
        $entry = RoleAuditEntryModel::query()->where('role_id', $editor->id)->latest('created_at')->firstOrFail();
        $this->assertSame(['permissions_added' => ['media.view_any']], $entry->changes);
    }

    public function test_it_can_run_twice(): void
    {
        $editor = RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);

        $this->runMigrationFile(database_path(self::MIGRATION));
        $this->runMigrationFile(database_path(self::MIGRATION));

        $this->assertSame(1, $editor->permissions()->where('key', 'media.view_any')->count());
        $this->assertSame(1, RoleAuditEntryModel::query()->where('role_id', $editor->id)->count());
    }

    public function test_it_runs_without_an_editor_role(): void
    {
        RoleModel::query()->where('name', 'editor')->delete();

        $this->runMigrationFile(database_path(self::MIGRATION));

        $this->assertSame(0, RoleAuditEntryModel::query()->count());
    }
}
