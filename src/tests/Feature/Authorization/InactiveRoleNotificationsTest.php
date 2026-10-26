<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\InactiveRolesManagerNotification;
use App\Notifications\InactiveRolesNotification;
use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use RuntimeException;
use Tests\TestCase;

/**
 * Losing the association role while holding roles that require it emails the person and
 * whoever could grant those roles. Anonymizing and regaining membership send nothing.
 */
final class InactiveRoleNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);

        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $this->role('treasury', 'Tesorería', ['events.view_any'], requiresMembership: true);
        Notification::fake();
    }

    public function test_losing_membership_notifies_the_person_and_who_can_grant_the_roles(): void
    {
        $person = $this->person(['socio', 'treasury']);
        $admin = UserModel::factory()->admin()->create();
        $this->role('role-manager', 'Gestión de roles', ['users.assign_roles', 'events.view_any']);
        $manager = $this->holderOf('role-manager');
        $this->role('secretary', 'Secretaría', ['users.assign_roles']);
        $secretary = $this->holderOf('secretary');

        $this->authorization->removeRole($person, 'socio');

        Notification::assertSentTo($person, InactiveRolesNotification::class);
        Notification::assertSentTo($admin, InactiveRolesManagerNotification::class);
        Notification::assertSentTo($manager, InactiveRolesManagerNotification::class);
        Notification::assertNotSentTo($secretary, InactiveRolesManagerNotification::class);
        Notification::assertNotSentTo($person, InactiveRolesManagerNotification::class);
        Notification::assertSentToTimes($admin, InactiveRolesManagerNotification::class, 1);
    }

    public function test_the_same_happens_when_membership_is_lost_through_sync(): void
    {
        $person = $this->person(['socio', 'treasury']);

        $this->authorization->syncRoles($person, ['treasury']);

        Notification::assertSentTo($person, InactiveRolesNotification::class);
    }

    public function test_the_texts_name_the_roles_and_the_person(): void
    {
        $person = $this->person(['socio', 'treasury']);
        $person->forceFill(['display_name' => 'Jane Roe'])->save();
        $admin = UserModel::factory()->admin()->create();

        $this->authorization->removeRole($person, 'socio');

        Notification::assertSentTo($person, InactiveRolesNotification::class, function ($notification) use ($person): bool {
            $mail = $notification->toMail($person);

            return str_contains($mail->subject, 'están en pausa')
                && str_contains(implode(' ', $mail->introLines), 'Tesorería');
        });
        Notification::assertSentTo($admin, InactiveRolesManagerNotification::class, function ($notification) use ($admin): bool {
            $mail = $notification->toMail($admin);

            return str_starts_with($mail->subject, 'Jane Roe tiene roles en pausa')
                && str_contains(implode(' ', $mail->introLines), 'Tesorería');
        });
    }

    public function test_markdown_in_names_does_not_become_a_link(): void
    {
        $person = $this->person(['socio', 'treasury']);
        $person->forceFill(['display_name' => '[Pulsa aquí](https://example.test)'])->save();
        $admin = UserModel::factory()->admin()->create();

        $this->authorization->removeRole($person, 'socio');

        Notification::assertSentTo($admin, InactiveRolesManagerNotification::class, function ($notification) use ($admin): bool {
            $html = (string) $notification->toMail($admin)->render();

            return ! str_contains($html, 'href="https://example.test')
                && str_contains($html, '[Pulsa aquí]');
        });
    }

    public function test_escape_keeps_the_visible_characters(): void
    {
        $this->assertSame('\[x\]\(https://example\.test\)', EscapesMarkdown::escape('[x](https://example.test)'));
    }

    public function test_the_mails_show_quotes_ampersands_and_brackets_as_typed(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $managerHtml = (string) (new InactiveRolesManagerNotification("Jane O'Brien \"JR\"", 'Gestión de D&D, Rol <b>'))->toMail($admin)->render();
        $personHtml = (string) (new InactiveRolesNotification('Gremio', 'Gestión de D&D'))->toMail($admin)->render();

        $managerText = html_entity_decode(strip_tags($managerHtml), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString("Jane O'Brien \"JR\"", $managerText);
        $this->assertStringContainsString('Gestión de D&D, Rol <b>', $managerText);
        $this->assertStringNotContainsString('<b>', $managerHtml);
        $this->assertStringContainsString('Gestión de D&D', html_entity_decode(strip_tags($personHtml), ENT_QUOTES | ENT_HTML5));
        foreach ([$managerHtml, $personHtml] as $html) {
            $this->assertStringNotContainsString('&amp;amp;', $html);
            $this->assertStringNotContainsString('&amp;#039;', $html);
            $this->assertStringNotContainsString('\\', $html);
        }
    }

    public function test_links_in_names_never_become_links(): void
    {
        $admin = UserModel::factory()->admin()->create();

        foreach (['[x](https://example.test)', '<https://example.test>', '[x]: https://example.test'] as $name) {
            $html = (string) (new InactiveRolesManagerNotification($name, 'Tesorería'))->toMail($admin)->render();

            $this->assertStringNotContainsString('href="https://example.test', $html, $name);
            $this->assertStringContainsString('example.test', $html, $name);
        }
    }

    public function test_a_recipient_whose_mail_cannot_be_queued_does_not_stop_the_others(): void
    {
        $person = $this->person(['socio', 'treasury']);
        $failing = UserModel::factory()->admin()->create();
        $other = UserModel::factory()->admin()->create();
        $fake = new class extends NotificationFake
        {
            public ?string $failFor = null;

            public function send($notifiables, $notification): void
            {
                foreach (Collection::wrap($notifiables) as $notifiable) {
                    if ($notifiable instanceof UserModel && $notifiable->id === $this->failFor) {
                        throw new RuntimeException('queue unavailable');
                    }
                }

                parent::send($notifiables, $notification);
            }
        };
        $fake->failFor = (string) $failing->id;
        Notification::swap($fake);
        $log = Log::spy();

        $this->authorization->removeRole($person, 'socio');

        $fake->assertSentTo($person, InactiveRolesNotification::class);
        $fake->assertSentTo($other, InactiveRolesManagerNotification::class);
        $this->assertFalse($this->authorization->hasRole($person, 'socio'));
        $log->shouldHaveReceived('error')->once();
    }

    public function test_a_rolled_back_removal_sends_nothing(): void
    {
        $person = $this->person(['socio', 'treasury']);
        UserModel::factory()->admin()->create();

        try {
            DB::transaction(function () use ($person): void {
                $this->authorization->removeRole($person, 'socio');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        Notification::assertNothingSent();
    }

    public function test_nothing_is_sent_without_roles_requiring_membership(): void
    {
        $person = $this->person(['socio']);
        UserModel::factory()->admin()->create();

        $this->authorization->removeRole($person, 'socio');

        Notification::assertNothingSent();
    }

    public function test_inactive_managers_are_not_notified(): void
    {
        $person = $this->person(['socio', 'treasury']);
        $this->role('board', 'Junta', ['users.assign_roles', 'events.view_any'], requiresMembership: true);
        $inactiveManager = $this->holderOf('board', isAssociationMember: false);

        $this->authorization->removeRole($person, 'socio');

        Notification::assertNotSentTo($inactiveManager, InactiveRolesManagerNotification::class);
    }

    public function test_anonymizing_sends_nothing(): void
    {
        $person = $this->person(['socio', 'treasury']);
        UserModel::factory()->admin()->create();

        app(UserServiceInterface::class)->anonymize((string) $person->id);

        Notification::assertNothingSent();
    }

    public function test_regaining_membership_sends_nothing(): void
    {
        $person = $this->person(['treasury']);
        UserModel::factory()->admin()->create();

        $this->authorization->assignRole($person, 'socio');
        $this->authorization->syncRoles($person, ['socio', 'treasury']);

        Notification::assertNothingSent();
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function role(string $name, string $displayName, array $permissionKeys, bool $requiresMembership = false): RoleModel
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => $displayName]);
        $role->requires_association_member = $requiresMembership;
        $role->save();

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    /**
     * @param  array<string>  $roleNames
     */
    private function person(array $roleNames): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach(RoleModel::query()->whereIn('name', $roleNames)->pluck('id')->all());

        return $user;
    }

    private function holderOf(string $roleName, bool $isAssociationMember = true): UserModel
    {
        $user = $this->person([$roleName]);
        if ($isAssociationMember) {
            $user->roles()->attach(RoleModel::query()->where('name', 'socio')->value('id'));
        }

        return $user;
    }
}
