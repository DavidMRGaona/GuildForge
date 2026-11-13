<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Proposals;

use App\Application\Proposals\DTOs\ProposalEligibilityDTO;
use App\Application\Proposals\DTOs\ProposalSettingsDTO;
use App\Application\Proposals\DTOs\ProposalTypeSettingsDTO;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalAccessLevel;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ProposalEligibilityServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function configure(ProposalTypeSettingsDTO $events, ?ProposalTypeSettingsDTO $articles = null): void
    {
        app(ProposalSettingsServiceInterface::class)->save(new ProposalSettingsDTO(
            events: $events,
            articles: $articles ?? new ProposalTypeSettingsDTO,
        ));
    }

    private function check(ProposalType $type, ?UserModel $user): ProposalEligibilityDTO
    {
        return app(ProposalEligibilityServiceInterface::class)->check($type, $user?->id);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $role, array $permissions = []): UserModel
    {
        $model = RoleModel::query()->firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)]);
        foreach ($permissions as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $model->permissions()->attach(PermissionModel::query()->firstOrCreate(['key' => $key], ['label' => $key, 'resource' => $resource, 'action' => $action])->id);
        }

        $user = UserModel::factory()->create();
        $user->roles()->attach($model->id);

        return $user;
    }

    public function test_nobody_may_propose_while_proposals_are_off(): void
    {
        $this->assertSame(ProposalEligibilityDTO::DISABLED, $this->check(ProposalType::Event, UserModel::factory()->admin()->create())->reason);
    }

    public function test_any_active_verified_account_may_propose_at_the_registered_level(): void
    {
        $this->configure(new ProposalTypeSettingsDTO(enabled: true));

        $this->assertTrue($this->check(ProposalType::Event, UserModel::factory()->create())->allowed);
        $this->assertSame(ProposalEligibilityDTO::DISABLED, $this->check(ProposalType::Article, UserModel::factory()->create())->reason, 'each type is turned on separately');
    }

    public function test_guests_inactive_and_unverified_accounts_may_not_propose(): void
    {
        $this->configure(new ProposalTypeSettingsDTO(enabled: true));
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '0');

        $this->assertSame(ProposalEligibilityDTO::AUTHENTICATION_REQUIRED, $this->check(ProposalType::Event, null)->reason);
        $this->assertSame(ProposalEligibilityDTO::EMAIL_UNVERIFIED, $this->check(ProposalType::Event, UserModel::factory()->unverified()->create())->reason, 'even with the site verification off');

        $deactivated = UserModel::factory()->create();
        $deactivated->delete();
        $this->assertSame(ProposalEligibilityDTO::ACCOUNT_INACTIVE, $this->check(ProposalType::Event, $deactivated)->reason);

        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $this->assertSame(ProposalEligibilityDTO::ACCOUNT_INACTIVE, $this->check(ProposalType::Event, $anonymized)->reason);

        $this->assertSame(ProposalEligibilityDTO::AUTHENTICATION_REQUIRED, app(ProposalEligibilityServiceInterface::class)->check(ProposalType::Event, 'not-a-uuid')->reason);
    }

    public function test_the_roles_level_lets_in_holders_of_a_chosen_role_only(): void
    {
        RoleModel::query()->create(['name' => 'tesoreria', 'display_name' => 'Tesorería']);
        $this->configure(new ProposalTypeSettingsDTO(enabled: true, accessLevel: ProposalAccessLevel::Roles, roles: ['tesoreria']));

        $this->assertTrue($this->check(ProposalType::Event, $this->userWithRole('tesoreria'))->allowed);
        $this->assertSame(ProposalEligibilityDTO::ROLE_NOT_ALLOWED, $this->check(ProposalType::Event, UserModel::factory()->create())->reason);
    }

    public function test_the_roles_level_without_roles_lets_nobody_in(): void
    {
        $this->configure(new ProposalTypeSettingsDTO(enabled: true, accessLevel: ProposalAccessLevel::Roles));

        $this->assertSame(ProposalEligibilityDTO::NO_ROLES_CONFIGURED, $this->check(ProposalType::Event, UserModel::factory()->admin()->create())->reason);
    }

    public function test_the_permission_level_needs_the_types_propose_permission(): void
    {
        $this->configure(
            new ProposalTypeSettingsDTO(enabled: true, accessLevel: ProposalAccessLevel::Permission),
            new ProposalTypeSettingsDTO(enabled: true, accessLevel: ProposalAccessLevel::Permission),
        );
        $user = $this->userWithRole('proponentes', ['events.propose']);

        $this->assertTrue($this->check(ProposalType::Event, $user)->allowed);
        $this->assertSame(ProposalEligibilityDTO::PERMISSION_DENIED, $this->check(ProposalType::Article, $user)->reason);
    }

    public function test_an_unreadable_level_lets_nobody_in(): void
    {
        $this->configure(new ProposalTypeSettingsDTO(enabled: true));
        app(SettingsServiceInterface::class)->set('proposals_events_access_level', 'everyone');

        $this->assertSame(ProposalEligibilityDTO::NO_ACCESS_LEVEL, $this->check(ProposalType::Event, UserModel::factory()->admin()->create())->reason);
    }
}
