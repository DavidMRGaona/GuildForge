<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RolesSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_fresh_install_gives_editor_media_view_any_but_not_delete(): void
    {
        // Both permissions exist, as after permissions:sync; only the suggested one is seeded
        foreach (['view_any', 'delete'] as $action) {
            PermissionModel::query()->firstOrCreate(
                ['key' => 'media.'.$action],
                ['label' => 'media.'.$action, 'resource' => 'media', 'action' => $action],
            );
        }

        $this->seed(RolesSeeder::class);

        $editor = RoleModel::query()->where('name', 'editor')->sole();
        $this->assertTrue($editor->permissions()->where('key', 'media.view_any')->exists());
        $this->assertFalse($editor->permissions()->where('key', 'media.delete')->exists());
    }
}
