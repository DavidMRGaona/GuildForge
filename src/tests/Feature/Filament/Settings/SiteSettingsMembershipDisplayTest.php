<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Settings;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Profiles\Events\ProfileMembershipDisplayChanged;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Profiles\Jobs\AnnounceMembershipDisplayForRolesJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Changing the association member role changes who is a member (core#824): a queued job announces every
 * account that held either role, so whoever depends on ProfileMembershipDisplayInterface asks again.
 */
final class SiteSettingsMembershipDisplayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_changing_the_association_role_queues_the_announcement_for_both_roles(): void
    {
        Queue::fake();
        app(SettingsServiceInterface::class)->set('association_member_role', 'socio');
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta directiva']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['association_member_role' => 'junta'])
            ->call('save');

        Queue::assertPushed(
            AnnounceMembershipDisplayForRolesJob::class,
            static fn (AnnounceMembershipDisplayForRolesJob $job): bool => $job->roleNames === ['socio', 'junta'],
        );
    }

    public function test_saving_without_changing_the_association_role_queues_nothing(): void
    {
        Queue::fake();
        app(SettingsServiceInterface::class)->set('association_member_role', 'socio');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Gremio nuevo'])
            ->call('save');

        Queue::assertNotPushed(AnnounceMembershipDisplayForRolesJob::class);
    }

    public function test_the_job_announces_every_account_holding_either_role_once_in_batches(): void
    {
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta']);
        $authorization = app(AuthorizationServiceInterface::class);
        $withSocio = UserModel::factory()->create();
        $withJunta = UserModel::factory()->create();
        $withBoth = UserModel::factory()->create();
        $deactivated = UserModel::factory()->create();
        $none = UserModel::factory()->create();
        $authorization->assignRole($withSocio, 'socio');
        $authorization->assignRole($withBoth, 'socio');
        $authorization->assignRole($deactivated, 'socio');
        $withJunta->roles()->attach($junta->id);
        $withBoth->roles()->attach($junta->id);
        $deactivated->delete();
        $announced = [];
        Event::listen(ProfileMembershipDisplayChanged::class, static function (ProfileMembershipDisplayChanged $event) use (&$announced): void {
            $announced[] = $event->userId;
        });

        (new AnnounceMembershipDisplayForRolesJob(['socio', 'junta'], batchSize: 2))->handle();

        $expected = [$withSocio->id, $withJunta->id, $withBoth->id, $deactivated->id];
        // Each account once, whatever the roles it holds
        $this->assertEqualsCanonicalizing($expected, $announced);
        $this->assertNotContains((string) $none->id, $announced);
    }

    public function test_the_job_with_no_roles_announces_nothing(): void
    {
        Event::fake([ProfileMembershipDisplayChanged::class]);

        (new AnnounceMembershipDisplayForRolesJob([]))->handle();

        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }
}
