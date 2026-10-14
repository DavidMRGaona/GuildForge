<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\UserServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "member" is the base role of every account: code that removes or replaces a user's roles
 * must never take it away. Every other role is added and removed as before, and anonymizing
 * an account still strips all of its roles.
 */
final class BaseRoleInAuthorizationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthorizationServiceInterface $authorization;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);

        foreach (['member', 'admin', 'editor', 'socio', 'junta'] as $name) {
            RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
        }

        $this->user = UserModel::factory()->create();
        $this->authorization->assignRole($this->user, 'member');
    }

    #[DataProvider('otherRoles')]
    public function test_any_other_role_is_added_and_removed_through_the_service(string $roleName): void
    {
        $this->authorization->assignRole($this->user, $roleName);
        $this->assertTrue($this->authorization->hasRole($this->user, $roleName));

        $this->authorization->removeRole($this->user, $roleName);

        $this->assertFalse($this->authorization->hasRole($this->user, $roleName));
        $this->assertFalse($this->user->roles()->where('name', $roleName)->exists());
        $this->assertTrue($this->authorization->hasRole($this->user, 'member'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherRoles(): array
    {
        return [
            'admin' => ['admin'],
            'editor' => ['editor'],
            'socio' => ['socio'],
            'custom role' => ['junta'],
        ];
    }

    public function test_removing_a_role_that_does_not_exist_changes_nothing(): void
    {
        $this->authorization->removeRole($this->user, 'does_not_exist');

        $this->assertSame(['member'], $this->authorization->getRoles($this->user));
    }

    public function test_sync_replaces_every_other_role(): void
    {
        $this->authorization->assignRole($this->user, 'admin');
        $this->authorization->assignRole($this->user, 'socio');

        $this->authorization->syncRoles($this->user, ['member', 'editor', 'junta']);

        $this->assertEqualsCanonicalizing(['member', 'editor', 'junta'], $this->authorization->getRoles($this->user));
        $this->assertEqualsCanonicalizing(['member', 'editor', 'junta'], $this->databaseRoles($this->user));
    }

    public function test_sync_does_not_give_the_base_role_to_a_user_without_it(): void
    {
        $user = UserModel::factory()->create();
        $this->authorization->assignRole($user, 'admin');

        $this->authorization->syncRoles($user, ['editor']);

        $this->assertSame(['editor'], $this->databaseRoles($user));
        $this->assertSame(['editor'], $this->authorization->getRoles($user));
    }

    public function test_anonymizing_still_removes_every_role(): void
    {
        $this->authorization->assignRole($this->user, 'socio');

        app(UserServiceInterface::class)->anonymize($this->user->id);

        $this->assertSame([], $this->databaseRoles($this->user));
        $this->assertSame([], $this->authorization->getRoles($this->user));
    }

    public function test_anonymizing_with_content_transfer_still_removes_every_role(): void
    {
        $this->authorization->assignRole($this->user, 'editor');

        app(UserServiceInterface::class)->anonymizeWithContentTransfer(
            new AnonymizeUserDTO(userId: $this->user->id, contentAction: 'keep'),
        );

        $this->assertSame([], $this->databaseRoles($this->user));
        $this->assertSame([], $this->authorization->getRoles($this->user));
    }

    /**
     * @return array<string>
     */
    private function databaseRoles(UserModel $user): array
    {
        /** @var array<string> $names */
        $names = $user->roles()->pluck('name')->all();

        return $this->sorted($names);
    }

    /**
     * @param  array<string>  $names
     * @return array<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
