<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * permissions:sync runs on every deploy: it is how new core permissions reach existing sites.
 */
final class PermissionsSyncCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sync_creates_the_menu_item_permissions(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();

        $this->assertSame(5, PermissionModel::query()->whereIn('key', [
            'menu_items.view_any',
            'menu_items.view',
            'menu_items.create',
            'menu_items.update',
            'menu_items.delete',
        ])->where('resource', 'menu_items')->count());
    }
}
