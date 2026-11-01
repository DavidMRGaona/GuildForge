<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Settings;

use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Knowledge\Jobs\SyncKnowledgeCatalogJob;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class SiteSettingsKnowledgeSyncTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_changing_the_site_name_dispatches_a_sync(): void
    {
        Queue::fake();
        app(SettingsServiceInterface::class)->set('guild_name', 'Gremio viejo');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Gremio nuevo'])
            ->call('save');

        Queue::assertPushed(SyncKnowledgeCatalogJob::class);
    }

    public function test_changing_the_association_role_dispatches_a_sync(): void
    {
        Queue::fake();
        app(SettingsServiceInterface::class)->set('association_member_role', 'socio');
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['association_member_role' => 'junta'])
            ->call('save');

        Queue::assertPushed(SyncKnowledgeCatalogJob::class);
    }

    public function test_saving_unrelated_settings_does_not_dispatch_a_sync(): void
    {
        Queue::fake();
        app(SettingsServiceInterface::class)->set('guild_name', 'Gremio');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Gremio', 'maintenance_message' => 'Volvemos pronto'])
            ->call('save');

        Queue::assertNotPushed(SyncKnowledgeCatalogJob::class);
    }

    public function test_rejected_association_role_change_does_not_dispatch_a_sync(): void
    {
        Queue::fake();
        app(SettingsServiceInterface::class)->set('association_member_role', 'socio');
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $board = RoleModel::query()->create(['name' => 'board', 'display_name' => 'Junta de gobierno', 'requires_association_member' => true]);
        $board->permissions()->attach(PermissionModel::query()->firstOrCreate(['key' => 'roles.delete'], ['label' => 'roles.delete', 'resource' => 'roles', 'action' => 'delete'])->id);
        UserModel::factory()->create()->roles()->attach([$junta->id, $board->id]);
        $this->actingAs($this->editorWithPermissions(['settings.manage', 'users.assign_roles', 'events.view_any']));

        Livewire::test(SiteSettings::class)
            ->fillForm(['association_member_role' => 'junta', 'guild_name' => 'Gremio nuevo'])
            ->call('save');

        $this->assertSame('socio', app(SettingsServiceInterface::class)->get('association_member_role'));
        Queue::assertNotPushed(SyncKnowledgeCatalogJob::class);
    }

    public function test_a_failing_sync_does_not_break_the_save(): void
    {
        config(['queue.default' => 'sync']);
        $synchronizer = Mockery::mock(KnowledgeCatalogSynchronizerInterface::class);
        $synchronizer->shouldReceive('sync')->andThrow(new RuntimeException('broken guide'));
        $this->app->instance(KnowledgeCatalogSynchronizerInterface::class, $synchronizer);
        Log::shouldReceive('warning')->once();
        app(SettingsServiceInterface::class)->set('guild_name', 'Gremio viejo');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Gremio nuevo'])
            ->call('save')
            ->assertNotified(Notification::make()->title(__('filament.settings.location.saved'))->success());

        $this->assertSame('Gremio nuevo', app(SettingsServiceInterface::class)->get('guild_name'));
    }
}
