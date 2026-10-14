<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "member" is any registered user; the association member is the role chosen in the site
 * settings ("socio" by default). Modules ask this service, never the role name directly.
 */
final class AssociationMembershipServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_default_role_is_used_when_nothing_is_configured(): void
    {
        $this->assertSame('socio', $this->service()->roleName());
    }

    public function test_the_configured_role_is_used(): void
    {
        $this->configure('junta');

        $this->assertSame('junta', $this->service()->roleName());
    }

    public function test_a_configured_role_is_trimmed(): void
    {
        $this->configure('  junta ');

        $this->assertSame('junta', $this->service()->roleName());
    }

    #[DataProvider('blankValues')]
    public function test_a_blank_setting_falls_back_to_the_default_role(string $value): void
    {
        $this->configure($value);

        $this->assertSame('socio', $this->service()->roleName());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankValues(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
        ];
    }

    #[DataProvider('forbiddenValues')]
    public function test_a_base_role_is_never_the_association_member_role(string $value): void
    {
        Log::spy();
        $this->configure($value);
        $user = UserModel::factory()->create();
        $this->grant($user, trim(strtolower($value)));

        $this->assertNull($this->service()->roleName());
        $this->assertFalse($this->service()->isAssociationMember($user));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenValues(): array
    {
        return [
            'admin' => ['admin'],
            'editor' => ['editor'],
            'member' => ['member'],
            'member with spaces and capitals' => [' Member '],
        ];
    }

    public function test_a_forbidden_setting_is_logged_once_per_request(): void
    {
        Log::spy();
        $this->configure('member');

        $this->service()->roleName();
        $this->service()->roleName();
        $this->service()->isAssociationMember(UserModel::factory()->create());

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_forbidden_setting_warning_is_logged_again_in_the_next_request(): void
    {
        Log::spy();
        $this->configure('member');

        $this->service()->roleName();
        // Laravel drops scoped instances when a new request or job starts
        $this->app->forgetScopedInstances();
        $this->service()->roleName();

        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_a_user_holding_the_configured_role_is_an_association_member(): void
    {
        $user = UserModel::factory()->create();
        $this->grant($user, 'socio');

        $this->assertTrue($this->service()->isAssociationMember($user));
    }

    public function test_a_user_holding_a_custom_configured_role_is_an_association_member(): void
    {
        $this->configure('junta');
        $user = UserModel::factory()->create();
        $this->grant($user, 'junta');

        $this->assertTrue($this->service()->isAssociationMember($user));
    }

    public function test_a_user_holding_another_role_is_not_an_association_member(): void
    {
        $user = UserModel::factory()->create();
        $this->grant($user, 'member');
        $this->grant($user, 'jugador');

        $this->assertFalse($this->service()->isAssociationMember($user));
    }

    public function test_an_admin_is_not_an_association_member_for_being_an_admin(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertFalse($this->service()->isAssociationMember($admin));
    }

    public function test_nobody_is_an_association_member_when_the_configured_role_does_not_exist(): void
    {
        $this->configure('inexistente');
        $user = UserModel::factory()->create();
        $this->grant($user, 'socio');

        $this->assertFalse($this->service()->isAssociationMember($user));
    }

    public function test_the_service_is_a_singleton(): void
    {
        $this->assertSame($this->service(), $this->service());
    }

    private function service(): AssociationMembershipServiceInterface
    {
        return app(AssociationMembershipServiceInterface::class);
    }

    private function configure(string $value): void
    {
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, $value);
    }

    private function grant(UserModel $user, string $roleName): void
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
