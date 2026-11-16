<?php

declare(strict_types=1);

namespace Tests\Feature\Profiles;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Where the user menu and the account settings page send the signed-in person to see their own public
 * profile (core#745): `auth.user.publicProfilePath`, worked out on the server from the account's profile
 * row, so the browser never builds an address of its own.
 */
final class SharedPublicProfilePathTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_account_with_a_profile_gets_the_path_of_its_public_profile(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana Ejemplo']);

        $this->assertSame('/miembros/'.$this->handleOf($user), $this->sharedUser($user)['publicProfilePath']);
        $this->assertSame('/miembros/ana-ejemplo', $this->sharedUser($user)['publicProfilePath']);
    }

    public function test_an_account_without_a_public_name_still_gets_the_path_of_its_own_profile(): void
    {
        $user = UserModel::factory()->create(['display_name' => null]);

        $path = $this->sharedUser($user)['publicProfilePath'];

        $this->assertSame('/miembros/'.$this->handleOf($user), $path);
        $this->assertStringStartsWith('/miembros/miembro-', $path);
    }

    public function test_a_hidden_profile_is_still_linked_for_its_owner(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana Ejemplo']);
        UserProfileModel::query()->whereKey($user->id)->update(['visibility' => ProfileVisibility::Hidden->value]);

        $this->assertSame('/miembros/'.$this->handleOf($user), $this->sharedUser($user)['publicProfilePath']);
    }

    public function test_an_account_without_a_profile_row_gets_no_path(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana Ejemplo']);
        UserProfileModel::query()->whereKey($user->id)->delete();

        $this->assertNull($this->sharedUser($user)['publicProfilePath']);
    }

    public function test_the_path_follows_the_handle_when_it_changes(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana Ejemplo']);
        $this->assertSame('/miembros/ana-ejemplo', $this->sharedUser($user)['publicProfilePath']);

        UserProfileModel::query()->whereKey($user->id)->update(['handle' => 'la-ana']);
        // The rows read are remembered for one request; the next request reads them again
        $this->app->forgetScopedInstances();

        $this->assertSame('/miembros/la-ana', $this->sharedUser($user)['publicProfilePath']);
    }

    public function test_the_path_is_never_the_account_name_or_email(): void
    {
        $user = UserModel::factory()->create([
            'name' => 'Nombre Privado',
            'email' => 'privado@example.com',
            'display_name' => 'Ana Ejemplo',
        ]);

        $path = $this->sharedUser($user)['publicProfilePath'];

        $this->assertStringNotContainsString('privado', $path);
    }

    public function test_a_guest_gets_no_user_and_so_no_path(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }

    public function test_the_user_is_still_shared_when_the_profile_cannot_be_read(): void
    {
        $log = Log::spy();
        $user = UserModel::factory()->create();
        Schema::drop('user_profiles');

        $shared = $this->sharedUser($user);

        $this->assertNull($shared['publicProfilePath']);
        $this->assertSame($user->id, $shared['id']);
        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message): bool => str_contains($message, 'publicProfilePath'),
        );
    }

    public function test_an_anonymized_account_gets_no_path(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana Ejemplo', 'anonymized_at' => now()]);

        $this->assertNull($this->sharedUser($user)['publicProfilePath']);
    }

    public function test_while_impersonating_the_path_is_the_one_of_the_impersonated_account(): void
    {
        $admin = UserModel::factory()->admin()->create(['display_name' => 'Admin Ejemplo']);
        $member = UserModel::factory()->create(['display_name' => 'Ana Ejemplo']);
        $this->actingAs($admin);
        app(ImpersonationServiceInterface::class)->start($admin, $member, ImpersonationMode::ReadOnly);

        $shared = $this->currentSharedUser();

        $this->assertSame($member->id, $shared['id']);
        $this->assertSame('/miembros/ana-ejemplo', $shared['publicProfilePath']);
    }

    public function test_viewing_as_roles_gets_no_path_because_the_throwaway_account_has_no_profile(): void
    {
        $admin = UserModel::factory()->admin()->create(['display_name' => 'Admin Ejemplo']);
        $this->actingAs($admin);
        app(ImpersonationServiceInterface::class)->startWithRoles(
            $admin,
            [(string) RoleModel::query()->where('name', 'socio')->value('id')],
            ImpersonationMode::ReadOnly,
        );

        $shared = $this->currentSharedUser();

        $this->assertNotSame($admin->id, $shared['id']);
        $this->assertNull($shared['publicProfilePath']);
    }

    private function handleOf(UserModel $user): string
    {
        return (string) UserProfileModel::query()->whereKey($user->id)->value('handle');
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedUser(UserModel $model): array
    {
        $this->actingAs($model);

        return $this->currentSharedUser();
    }

    /**
     * The shared `auth.user` of a request made as whoever is signed in now.
     *
     * @return array<string, mixed>
     */
    private function currentSharedUser(): array
    {
        $shared = null;

        $this->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$shared): void {
                $shared = $page->toArray()['props']['auth']['user'];
            });

        $this->assertIsArray($shared);
        $this->assertArrayHasKey('publicProfilePath', $shared);

        /** @var array<string, mixed> $shared */
        return $shared;
    }
}
