<?php

declare(strict_types=1);

namespace Tests\Feature\Profiles;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Profiles\DTOs\ProfileUpdateDTO;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Events\UserActiveRolesChanged;
use App\Domain\Profiles\Events\ProfileMembershipDisplayChanged;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\Services\ProfileMembershipDisplayAnnouncer;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/**
 * ProfileMembershipDisplayChanged (core#824) comes after the commit, once per account and transaction, every
 * time what ProfileMembershipDisplayInterface answers for an account may have changed.
 */
final class ProfileMembershipDisplayChangedTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  Closure(UserModel, UserProfileModel): void  $change
     */
    #[DataProvider('changes')]
    public function test_a_change_that_can_alter_the_answer_announces_the_account_once(Closure $change): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        $change($user, $profile);

        $this->assertAnnouncedOnce((string) $user->id);
    }

    /**
     * @return array<string, array{Closure(UserModel, UserProfileModel): void}>
     */
    public static function changes(): array
    {
        return [
            'badge turned off' => [static function (UserModel $user, UserProfileModel $profile): void {
                $profile->update(['show_membership' => false]);
            }],
            'visibility changed' => [static function (UserModel $user, UserProfileModel $profile): void {
                $profile->update(['visibility' => ProfileVisibility::Hidden]);
            }],
            'public name removed' => [static function (UserModel $user): void {
                $user->update(['display_name' => null]);
            }],
            'public name changed' => [static function (UserModel $user): void {
                $user->update(['display_name' => 'Otro nombre']);
            }],
            'account deactivated' => [static function (UserModel $user): void {
                $user->delete();
            }],
            'account anonymized' => [static function (UserModel $user): void {
                app(UserServiceInterface::class)->anonymize((string) $user->id);
            }],
            'profile forgotten' => [static function (UserModel $user): void {
                app(UserProfileServiceInterface::class)->forget((string) $user->id);
            }],
            'association role removed' => [static function (UserModel $user): void {
                app(AuthorizationServiceInterface::class)->removeRole($user, 'socio');
            }],
            'roles synced without it' => [static function (UserModel $user): void {
                app(AuthorizationServiceInterface::class)->syncRoles($user, ['member']);
            }],
        ];
    }

    public function test_deleting_an_account_for_good_announces_it(): void
    {
        $user = $this->member();
        Event::fake([ProfileMembershipDisplayChanged::class]);

        $user->forceDelete();

        Event::assertDispatched(
            ProfileMembershipDisplayChanged::class,
            static fn (ProfileMembershipDisplayChanged $event): bool => $event->userId === (string) $user->id,
        );
    }

    public function test_the_association_role_turning_on_or_off_with_two_factor_announces_the_account(): void
    {
        $user = $this->member();
        Event::fake([ProfileMembershipDisplayChanged::class]);

        // What FollowTwoFactorChanges dispatches when two-factor goes on or off
        event(new UserActiveRolesChanged((string) $user->id, ['socio'], []));
        $this->assertAnnouncedOnce((string) $user->id);

        Event::fake([ProfileMembershipDisplayChanged::class]);
        event(new UserActiveRolesChanged((string) $user->id, [], ['socio']));
        $this->assertAnnouncedOnce((string) $user->id);
    }

    public function test_other_roles_turning_on_or_off_announce_nothing(): void
    {
        $user = $this->member();
        Event::fake([ProfileMembershipDisplayChanged::class]);

        event(new UserActiveRolesChanged((string) $user->id, ['junta'], ['moderacion']));

        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_restoring_an_account_announces_it(): void
    {
        $user = $this->member();
        $user->delete();
        Event::fake([ProfileMembershipDisplayChanged::class]);

        $user->restore();

        $this->assertAnnouncedOnce((string) $user->id);
    }

    public function test_turning_the_badge_on_or_gaining_the_role_announces_the_account(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        UserProfileModel::query()->findOrFail($user->id)->update(['show_membership' => true]);
        $this->assertAnnouncedOnce((string) $user->id);

        Event::fake([ProfileMembershipDisplayChanged::class]);
        app(AuthorizationServiceInterface::class)->assignRole($user, 'socio');
        $this->assertAnnouncedOnce((string) $user->id);

        Event::fake([ProfileMembershipDisplayChanged::class]);
        app(AuthorizationServiceInterface::class)->syncRoles($user, ['member']);
        $this->assertAnnouncedOnce((string) $user->id);

        Event::fake([ProfileMembershipDisplayChanged::class]);
        app(AuthorizationServiceInterface::class)->syncRoles($user, ['member', 'socio']);
        $this->assertAnnouncedOnce((string) $user->id);
    }

    public function test_saving_from_the_editor_announces_when_the_badge_or_visibility_changes(): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        app(UserProfileServiceInterface::class)->update((string) $user->id, $this->update($profile, showMembership: false));

        $this->assertAnnouncedOnce((string) $user->id);
    }

    public function test_saving_from_the_editor_without_those_changes_announces_nothing(): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        app(UserProfileServiceInterface::class)->update((string) $user->id, $this->update($profile, bio: 'Juego a rol'));

        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_changes_that_cannot_alter_the_answer_announce_nothing(): void
    {
        $user = $this->member();
        RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta']);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        // The panel's reset only takes the bio and the banner away
        UserProfileModel::query()->findOrFail($user->id)->update(['bio' => 'Hola', 'banner_public_id' => 'profiles/banners/2026/11/a.jpg']);
        app(UserProfileServiceInterface::class)->resetContent((string) $user->id);
        // Another role, another account detail, the same name saved again
        app(AuthorizationServiceInterface::class)->assignRole($user, 'junta');
        app(AuthorizationServiceInterface::class)->removeRole($user, 'junta');
        $user->update(['name' => 'Nombre de cuenta', 'display_name' => $user->display_name]);

        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_nothing_is_announced_before_the_commit_nor_after_a_rollback(): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        try {
            DB::transaction(function () use ($profile): void {
                $profile->update(['show_membership' => false]);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
            // Expected
        }

        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_an_account_is_announced_once_per_transaction(): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        DB::transaction(function () use ($user, $profile): void {
            $profile->update(['show_membership' => false]);
            $profile->update(['visibility' => ProfileVisibility::Hidden]);
            $user->update(['display_name' => 'Cambiado']);
            app(AuthorizationServiceInterface::class)->removeRole($user, 'socio');
            Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
        });

        $this->assertAnnouncedOnce((string) $user->id);
    }

    public function test_a_savepoint_that_rolls_back_does_not_swallow_the_announcement_of_its_transaction(): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        DB::transaction(function () use ($user, $profile): void {
            try {
                DB::transaction(function () use ($profile): void {
                    $profile->update(['show_membership' => false]);

                    throw new RuntimeException('rolled back');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $user->update(['display_name' => 'Cambiado']);
        });

        $this->assertAnnouncedOnce((string) $user->id);
    }

    public function test_a_listener_that_fails_never_reaches_the_change_and_is_warned_once(): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        Cache::flush();
        Event::listen(ProfileMembershipDisplayChanged::class, static function (): never {
            throw new RuntimeException('queue down: secret@example.com');
        });
        $log = Log::spy();

        $profile->update(['show_membership' => false]);
        $profile->update(['show_membership' => true]);

        $this->assertTrue(UserProfileModel::query()->findOrFail($user->id)->show_membership);
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => ($context['exception'] ?? null) === RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret@example.com'),
        );
    }

    public function test_nothing_is_announced_while_impersonating(): void
    {
        $user = $this->member();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        $context = Mockery::mock(ImpersonationContextInterface::class);
        $context->shouldReceive('isActive')->andReturn(true);
        $this->app->instance(ImpersonationContextInterface::class, $context);
        $this->app->forgetInstance(ProfileMembershipDisplayAnnouncer::class);
        Event::fake([ProfileMembershipDisplayChanged::class]);

        $profile->update(['show_membership' => false]);

        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_the_event_carries_only_the_id(): void
    {
        $event = new ProfileMembershipDisplayChanged('0b5f2c9e-8a54-4a43-8f55-3c0f8d2f9a11');

        $this->assertSame(['userId'], array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($event))->getProperties(),
        ));
    }

    private function member(): UserModel
    {
        $user = UserModel::factory()->create(['display_name' => fake()->unique()->userName()]);
        app(AuthorizationServiceInterface::class)->assignRole($user, 'socio');
        UserProfileModel::query()->whereKey($user->id)->update(['show_membership' => true, 'visibility' => ProfileVisibility::Members->value]);

        return $user;
    }

    private function update(UserProfileModel $profile, ?bool $showMembership = null, ?string $bio = null): ProfileUpdateDTO
    {
        return new ProfileUpdateDTO(
            handle: $profile->handle,
            visibility: $profile->visibility,
            bio: $bio,
            showMembership: $showMembership ?? $profile->show_membership,
            sections: [],
            stats: [],
            offeredSections: [],
            offeredStats: [],
        );
    }

    private function assertAnnouncedOnce(string $userId): void
    {
        Event::assertDispatchedTimes(ProfileMembershipDisplayChanged::class, 1);
        Event::assertDispatched(
            ProfileMembershipDisplayChanged::class,
            static fn (ProfileMembershipDisplayChanged $event): bool => $event->userId === $userId,
        );
    }
}
