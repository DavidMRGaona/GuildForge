<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Media;

use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class MediaForgetSourceCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function usage(string $type, string $recordId, ?string $module = null): void
    {
        MediaUsageModel::query()->create([
            'file_id' => 'files/'.$recordId,
            'source_type' => $type,
            'module' => $module,
            'record_id' => $recordId,
            'role' => 'Imagen',
            'title' => 'Registro',
        ]);
    }

    public function test_refuses_active_source(): void
    {
        $this->usage('events', 'e1');

        $this->artisanCommand('media:forget-source', ['type' => 'events', '--force' => true])
            ->expectsOutputToContain('"events" belongs to an active media source')
            ->assertFailed();

        $this->assertSame(1, MediaUsageModel::query()->where('source_type', 'events')->count());
    }

    public function test_refuses_a_type_of_a_module_that_is_disabled_but_installed(): void
    {
        // Its source is not registered while the module is disabled, yet enabling it again needs its files
        ModuleModel::factory()->disabled()->create(['name' => 'game-tables']);
        $this->usage('gametables.tables', 't1', 'game-tables');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])
            ->expectsOutputToContain('belongs to the module "game-tables", which is still installed')
            ->assertFailed();

        $this->assertSame(1, MediaUsageModel::query()->where('source_type', 'gametables.tables')->count());
    }

    public function test_deletes_rows_of_a_module_that_is_no_longer_installed(): void
    {
        ModuleModel::factory()->disabled()->create(['name' => 'tournaments']);
        $this->usage('gametables.tables', 't1', 'game-tables');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])
            ->expectsOutputToContain('Deleted 1 usage rows of type "gametables.tables".')
            ->assertSuccessful();

        $this->assertFalse(MediaUsageModel::query()->where('source_type', 'gametables.tables')->exists());
    }

    public function test_deletes_rows_of_inactive_type_with_force(): void
    {
        $this->usage('gametables.tables', 't1');
        $this->usage('gametables.tables', 't2');
        $this->usage('events', 'e1');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])
            ->expectsOutputToContain('Deleted 2 usage rows of type "gametables.tables".')
            ->assertSuccessful();

        $this->assertFalse(MediaUsageModel::query()->where('source_type', 'gametables.tables')->exists());
        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'events')->exists());
    }

    public function test_asks_before_deleting_and_keeps_the_rows_when_declined(): void
    {
        $this->usage('gametables.tables', 't1');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables'])
            ->expectsConfirmation('Delete 1 usage rows of type "gametables.tables"? Their files will count as unused.', 'no')
            ->expectsOutputToContain('Nothing deleted.')
            ->assertSuccessful();

        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'gametables.tables')->exists());
    }

    public function test_reports_a_type_without_rows(): void
    {
        $this->artisanCommand('media:forget-source', ['type' => 'nothing.here', '--force' => true])
            ->expectsOutputToContain('No usage rows of type "nothing.here".')
            ->assertSuccessful();
    }
}
