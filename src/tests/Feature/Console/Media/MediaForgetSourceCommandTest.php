<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Media;

use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaSourceSyncState;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_forgetting_a_type_drops_its_refresh_failure(): void
    {
        // Nothing of the type is left to conceal (core#844)
        $state = $this->app->make(MediaSourceSyncState::class);
        $this->usage('gametables.tables', 't1');
        $state->markRefreshFailed('gametables.tables');
        $state->markRefreshFailed('events');
        $state->markRefreshFailed('nothing.here');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])->assertSuccessful();
        $this->artisanCommand('media:forget-source', ['type' => 'nothing.here', '--force' => true])->assertSuccessful();

        // A type without rows keeps it: nothing tells whether its module is still installed
        $this->assertSame(['events', 'nothing.here'], array_keys($state->refreshFailures() ?? []));
    }

    public function test_forgetting_a_type_drops_its_fingerprint(): void
    {
        // Reinstalled with the same version and its data kept, it must not count as up to date without rows
        $state = $this->app->make(MediaSourceSyncState::class);
        $state->record(['gametables.tables' => 'fingerprint', 'events' => 'other'], []);
        $this->usage('gametables.tables', 't1');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])->assertSuccessful();

        $this->assertSame(['events' => 'other'], $state->all());
    }

    public function test_forgetting_a_type_without_rows_drops_its_fingerprint_too(): void
    {
        // Forgetting a fingerprint only conceals until the next sync, so it is safe whoever owns the type
        $state = $this->app->make(MediaSourceSyncState::class);
        $state->record(['gametables.tables' => 'fingerprint', 'events' => 'other'], []);

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])
            ->expectsOutputToContain('No usage rows of type "gametables.tables".')
            ->assertSuccessful();

        $this->assertSame(['events' => 'other'], $state->all());
    }

    public function test_a_fingerprint_that_cannot_be_forgotten_keeps_every_row(): void
    {
        $this->usage('gametables.tables', 't1');
        Schema::drop('settings');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])
            ->expectsOutputToContain('The sync state of "gametables.tables" could not be updated; nothing was deleted.')
            ->assertFailed();

        $this->assertSame(1, MediaUsageModel::query()->where('source_type', 'gametables.tables')->count());
    }

    public function test_a_refresh_failure_that_cannot_be_dropped_ends_with_a_clear_message(): void
    {
        $state = $this->app->make(MediaSourceSyncState::class);
        $state->record(['gametables.tables' => 'fingerprint'], []);
        $this->usage('gametables.tables', 't1');
        Schema::drop('media_source_refresh_failures');

        $this->artisanCommand('media:forget-source', ['type' => 'gametables.tables', '--force' => true])
            ->expectsOutputToContain('Deleted 1 usage rows of type "gametables.tables".')
            ->expectsOutputToContain('The refresh failure of "gametables.tables" could not be cleared')
            ->doesntExpectOutputToContain('Stack trace')
            ->assertFailed();

        // The rows are gone and the type is out of date: the failure left behind only keeps it concealed
        $this->assertFalse(MediaUsageModel::query()->where('source_type', 'gametables.tables')->exists());
        $this->assertSame([], $state->all());
    }
}
