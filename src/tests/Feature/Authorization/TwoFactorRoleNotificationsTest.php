<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Domain\Events\UserActiveRolesChanged;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\TwoFactorRolesPausedNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Authorization\ManagesTwoFactorRoles;
use Tests\TestCase;

/**
 * Who is told when roles pause for want of two-factor authentication (core#772): only the
 * person, by mail and in the bell, with a link to «Seguridad»; the modules through
 * UserActiveRolesChanged.
 */
final class TwoFactorRoleNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ManagesTwoFactorRoles;

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);
        $this->roleRequiringTwoFactor('tesoreria', ['memberships:fees.view_any']);
        RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);
    }

    public function test_being_given_the_role_without_two_factor_tells_only_the_person(): void
    {
        $person = UserModel::factory()->create();
        $manager = UserModel::factory()->admin()->create();
        Notification::fake();

        $this->authorization->assignRole($person, 'tesoreria');

        Notification::assertSentTo($person, TwoFactorRolesPausedNotification::class, function (TwoFactorRolesPausedNotification $notification) use ($person): bool {
            $site = $notification->toSiteNotification($person);

            return str_ends_with($site->url, '/perfil?tab=seguridad')
                && str_contains($site->body ?? '', 'Tesoreria');
        });
        Notification::assertNotSentTo($manager, TwoFactorRolesPausedNotification::class);
    }

    public function test_being_given_the_role_with_two_factor_on_tells_nobody(): void
    {
        $person = UserModel::factory()->create();
        $this->turnOnTwoFactor($person);
        Notification::fake();

        $this->authorization->syncRoles($person, ['tesoreria']);

        Notification::assertNothingSentTo($person);
    }

    public function test_syncing_roles_names_only_the_new_roles_that_pause(): void
    {
        $person = $this->accountHolding(['tesoreria']);
        $this->roleRequiringTwoFactor('secretaria');
        Notification::fake();

        $this->authorization->syncRoles($person, ['tesoreria', 'secretaria', 'editor']);

        Notification::assertSentTo($person, TwoFactorRolesPausedNotification::class, function (TwoFactorRolesPausedNotification $notification) use ($person): bool {
            $body = $notification->toSiteNotification($person)->body ?? '';

            return str_contains($body, 'Secretaria') && ! str_contains($body, 'Tesoreria');
        });
    }

    public function test_a_reset_tells_the_person_and_the_modules(): void
    {
        $person = $this->accountHolding(['tesoreria']);
        $this->turnOnTwoFactor($person);
        Notification::fake();
        Event::fake([UserActiveRolesChanged::class]);

        app(TwoFactorResetInterface::class)->reset((string) $person->id, null);

        Notification::assertSentTo($person, TwoFactorRolesPausedNotification::class);
        Event::assertDispatched(UserActiveRolesChanged::class, fn (UserActiveRolesChanged $event): bool => $event->userId === $person->id
            && $event->rolesPaused === ['tesoreria'] && $event->rolesActivated === []);
    }

    public function test_turning_it_on_tells_the_modules_which_roles_came_back(): void
    {
        $person = $this->accountHolding(['tesoreria', 'editor']);
        Event::fake([UserActiveRolesChanged::class]);

        $this->turnOnTwoFactor($person);

        Event::assertDispatched(UserActiveRolesChanged::class, fn (UserActiveRolesChanged $event): bool => $event->userId === $person->id
            && $event->rolesActivated === ['tesoreria'] && $event->rolesPaused === []);
    }

    public function test_turning_it_on_without_roles_that_require_it_tells_nobody(): void
    {
        $person = $this->accountHolding(['editor']);
        Event::fake([UserActiveRolesChanged::class]);

        $this->turnOnTwoFactor($person);

        Event::assertNotDispatched(UserActiveRolesChanged::class);
    }

    public function test_the_mail_links_to_the_security_tab(): void
    {
        $person = UserModel::factory()->create();
        $mail = (new TwoFactorRolesPausedNotification('Example guild', 'Tesorería'))->toMail($person);

        $this->assertStringEndsWith('/perfil?tab=seguridad', (string) $mail->actionUrl);
        $this->assertStringContainsString('Tesorería', implode(' ', $mail->introLines));
    }
}
