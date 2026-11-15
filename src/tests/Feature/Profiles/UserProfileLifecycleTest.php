<?php

declare(strict_types=1);

namespace Tests\Feature\Profiles;

use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Profiles\DTOs\ProfileCreationResultDTO;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Closure;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Every path that creates or changes an account keeps its public profile row in step, and none of them
 * ever fails because of it.
 */
final class UserProfileLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registering_creates_the_profile_with_a_random_address(): void
    {
        $this->post('/registro', [
            'name' => 'Nuevo usuario',
            'email' => 'nuevo@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect();

        $user = UserModel::query()->where('email', 'nuevo@example.com')->firstOrFail();
        $profile = UserProfileModel::query()->findOrFail($user->id);
        $this->assertMatchesRegularExpression('/^miembro-(?=[a-z]*[2-9])[abcdefghjkmnpqrstuvwxyz23456789]{6}$/', $profile->handle);
    }

    public function test_an_account_is_created_and_the_error_logged_when_its_profile_cannot_be(): void
    {
        $this->app->instance(UserProfileServiceInterface::class, $this->failingProfiles());
        $log = Log::spy();

        $user = UserModel::factory()->create();

        $this->assertTrue(UserModel::query()->whereKey($user->id)->exists());
        $log->shouldHaveReceived('error')->once()->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'profile')
                && $context['user_id'] === (string) $user->id,
        );
    }

    public function test_registering_never_fails_because_of_the_profile(): void
    {
        $this->app->instance(UserProfileServiceInterface::class, $this->failingProfiles());
        Log::spy();

        $this->post('/registro', [
            'name' => 'Nuevo usuario',
            'email' => 'nuevo@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('home'));

        $this->assertTrue(UserModel::query()->where('email', 'nuevo@example.com')->exists());
    }

    public function test_a_public_name_change_that_cannot_move_the_address_is_still_saved(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->app->instance(UserProfileServiceInterface::class, $this->failingProfiles());
        $log = Log::spy();

        $user->update(['display_name' => 'Bea']);

        $this->assertSame('Bea', $user->fresh()?->display_name);
        $log->shouldHaveReceived('error')->once();
    }

    public function test_the_public_name_set_from_the_profile_moves_the_address(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['display_name' => null]);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'display_name' => 'Ana Ruiz',
            'email' => $user->email,
        ])->assertRedirect();

        $this->assertSame('ana-ruiz', UserProfileModel::query()->findOrFail($user->id)->handle);
    }

    public function test_the_public_name_set_from_the_panel_moves_the_address(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['display_name' => 'Bea Ruiz'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('bea-ruiz', UserProfileModel::query()->findOrFail($user->id)->handle);
        $this->assertTrue(UserProfileHandleModel::query()->whereKey(ProfileHandle::hashOf('ana'))->exists());
    }

    /**
     * @param  Closure(UserServiceInterface, string): void  $anonymize
     */
    #[DataProvider('anonymizers')]
    public function test_anonymizing_deletes_the_profile_and_keeps_only_the_fingerprint_of_its_addresses(Closure $anonymize): void
    {
        $releaser = Mockery::mock(MediaFileReleaserInterface::class);
        $releaser->shouldReceive('release')->once()->with('profiles/banners/2026/11/banner.jpg');
        $this->app->instance(MediaFileReleaserInterface::class, $releaser);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $user->update(['display_name' => 'Bea']);
        UserProfileModel::query()->whereKey($user->id)->update(['bio' => 'Juego a rol los viernes', 'banner_public_id' => 'profiles/banners/2026/11/banner.jpg']);

        $anonymize(app(UserServiceInterface::class), (string) $user->id);

        $this->assertNull(UserProfileModel::query()->find($user->id));
        $rows = UserProfileHandleModel::query()->get();
        $this->assertEqualsCanonicalizing([ProfileHandle::hashOf('ana'), ProfileHandle::hashOf('bea')], $rows->pluck('handle_hash')->all());
        foreach ($rows as $row) {
            $this->assertNull($row->handle);
            $this->assertNull($row->user_id);
        }

        $newcomer = UserModel::factory()->create(['display_name' => 'Bea']);
        $this->assertSame('bea-2', UserProfileModel::query()->findOrFail($newcomer->id)->handle);
    }

    /**
     * @return array<string, array{Closure(UserServiceInterface, string): void}>
     */
    public static function anonymizers(): array
    {
        return [
            'anonymize' => [static function (UserServiceInterface $service, string $userId): void {
                $service->anonymize($userId);
            }],
            'anonymize with content transfer' => [static function (UserServiceInterface $service, string $userId): void {
                $service->anonymizeWithContentTransfer(new AnonymizeUserDTO($userId, 'delete'));
            }],
        ];
    }

    public function test_deleting_an_account_for_good_keeps_its_address_reserved(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $user->forceDelete();

        $row = UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana'));
        $this->assertNull($row->handle);
        $this->assertNull($row->user_id);
        $newcomer = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->assertSame('ana-2', UserProfileModel::query()->findOrFail($newcomer->id)->handle);
    }

    public function test_deactivating_an_account_keeps_its_profile(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $user->delete();

        $this->assertSame('ana', UserProfileModel::query()->findOrFail($user->id)->handle);
    }

    public function test_the_command_creates_the_missing_profiles(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        UserProfileModel::query()->whereKey($user->id)->delete();

        $this->artisanCommand('profiles:create-missing', ['--dry-run' => true])
            ->expectsOutputToContain('1 perfil se crearía')
            ->assertSuccessful();
        $this->assertNull(UserProfileModel::query()->find($user->id));

        $this->artisanCommand('profiles:create-missing', ['--chunk' => 10])
            ->expectsOutputToContain('1 perfil creado')
            ->assertSuccessful();
        $this->assertSame('ana', UserProfileModel::query()->findOrFail($user->id)->handle);

        $this->artisanCommand('profiles:create-missing')
            ->expectsOutputToContain('0 perfiles creados')
            ->assertSuccessful();
    }

    public function test_the_command_realigns_addresses_apart_from_the_profiles_it_creates(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        DB::table('users')->where('id', $user->id)->update(['display_name' => 'Bea']);

        $this->artisanCommand('profiles:create-missing', ['--dry-run' => true])
            ->expectsOutputToContain('0 perfiles se crearían')
            ->expectsOutputToContain('1 dirección se realinearía con el nombre público')
            ->assertSuccessful();
        $this->assertSame('ana', UserProfileModel::query()->findOrFail($user->id)->handle);

        $this->artisanCommand('profiles:create-missing')
            ->expectsOutputToContain('0 perfiles creados')
            ->expectsOutputToContain('1 dirección realineada con el nombre público')
            ->assertSuccessful();
        $this->assertSame('bea', UserProfileModel::query()->findOrFail($user->id)->handle);
    }

    public function test_the_command_fails_when_a_profile_could_not_be_created(): void
    {
        $profiles = Mockery::mock(UserProfileServiceInterface::class);
        $profiles->shouldReceive('createMissing')->once()->with(false, 500)->andReturn(new ProfileCreationResultDTO(created: 2, realigned: 0, failed: 1));
        $this->app->instance(UserProfileServiceInterface::class, $profiles);

        $this->artisanCommand('profiles:create-missing')
            ->expectsOutputToContain('2 perfiles creados')
            ->expectsOutputToContain('1 cuenta se quedó sin perfil o sin realinear')
            ->assertFailed();
    }

    public function test_the_missing_profiles_are_created_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn (Event $event): bool => str_contains($event->command ?? '', 'profiles:create-missing')
                && $event->expression === '0 0 * * *'
        ));
    }

    private function failingProfiles(): UserProfileServiceInterface
    {
        $profiles = Mockery::mock(UserProfileServiceInterface::class);
        $profiles->shouldReceive('createFor')->andThrow(new RuntimeException('profiles unavailable'));
        $profiles->shouldReceive('followPublicName')->andThrow(new RuntimeException('profiles unavailable'));

        return $profiles;
    }
}
