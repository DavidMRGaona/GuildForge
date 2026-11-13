<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Filament\Resources\UserResource;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\InactiveRolesManagerNotification;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Inactive role notices also reach the bell; the other core.account mails do not.
 */
final class InactiveRolesSiteNoticeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SiteNotificationServiceInterface $notices;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->notices = app(SiteNotificationServiceInterface::class);
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $role = RoleModel::query()->firstOrCreate(['name' => 'treasury'], ['display_name' => 'Tesorería']);
        $role->requires_association_member = true;
        $role->save();
    }

    public function test_the_person_gets_a_notice_linking_to_their_profile(): void
    {
        $person = $this->person();

        app(AuthorizationServiceInterface::class)->removeRole($person, 'socio');

        $items = $this->notices->latest($person, 10);
        $this->assertCount(1, $items);
        $this->assertSame(route('profile.show', absolute: false), $items[0]->url);
        $this->assertStringContainsString('Tesorería', $items[0]->title.$items[0]->body);
    }

    public function test_the_manager_gets_a_notice_linking_to_the_person_in_the_panel(): void
    {
        $person = $this->person();
        $admin = UserModel::factory()->admin()->create();

        app(AuthorizationServiceInterface::class)->removeRole($person, 'socio');

        $items = $this->notices->latest($admin, 10);
        $this->assertCount(1, $items);
        $this->assertSame(
            UserResource::getUrl('edit', ['record' => $person->id], isAbsolute: false),
            $items[0]->url,
        );
        $this->assertStringNotContainsString('http', $items[0]->url);
    }

    public function test_role_names_stay_plain_text(): void
    {
        $person = $this->person();
        $person->forceFill(['display_name' => 'Ana [x](y) & Co'])->save();
        RoleModel::query()->where('name', 'treasury')->update(['display_name' => 'D&D <b>']);
        $admin = UserModel::factory()->admin()->create();

        app(AuthorizationServiceInterface::class)->removeRole($person, 'socio');

        $text = $this->notices->latest($admin, 10)[0]->title.' '.$this->notices->latest($admin, 10)[0]->body;
        $this->assertStringContainsString('D&D <b>', $text);
        $this->assertStringNotContainsString('\\', $text);
    }

    public function test_notices_about_the_same_person_group_and_forget_the_actor(): void
    {
        $person = $this->person();
        $admin = UserModel::factory()->admin()->create();

        app(AuthorizationServiceInterface::class)->removeRole($person, 'socio');
        $this->notices->forgetActor((string) $person->id);

        $this->assertSame(0, $this->notices->unreadCount($admin));
    }

    public function test_a_manager_who_can_no_longer_grant_the_roles_gets_no_notice(): void
    {
        $person = $this->person();
        $manager = UserModel::factory()->create();
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => 'users.assign_roles'],
            ['label' => 'x', 'resource' => 'users', 'action' => 'assign_roles'],
        );
        $extra = PermissionModel::query()->firstOrCreate(
            ['key' => 'events.view_any'],
            ['label' => 'y', 'resource' => 'events', 'action' => 'view_any'],
        );
        RoleModel::query()->where('name', 'treasury')->firstOrFail()->permissions()->syncWithoutDetaching([$extra->id]);
        $held = RoleModel::query()->firstOrCreate(['name' => 'secretary'], ['display_name' => 'Secretaría']);
        $held->permissions()->syncWithoutDetaching([$permission->id]);
        $manager->roles()->attach($held->id);

        $notification = new InactiveRolesManagerNotification('Ana', 'Tesorería', (string) $person->id, ['treasury']);

        $this->assertNull($notification->toSiteNotification($manager));
    }

    public function test_welcome_and_new_email_notices_stay_out_of_the_bell(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new WelcomeNotification('Example site'));
        $user->notify(new VerifyPendingEmailNotification('new@example.com'));

        $this->assertSame(0, $this->notices->unreadCount($user));
        $this->assertSame([], $this->notices->latest($user, 10));
    }

    private function person(): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->whereIn('name', ['socio', 'treasury'])->pluck('id')->all());

        return $user;
    }
}
