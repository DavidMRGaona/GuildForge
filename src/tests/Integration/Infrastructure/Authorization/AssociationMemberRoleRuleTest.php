<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Application\Authorization\Services\AssociationMemberRoleRule;
use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class AssociationMemberRoleRuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_default_role_is_the_association_member_role(): void
    {
        $this->assertTrue($this->rule()->isAssociationMemberRoleName('socio'));
        $this->assertFalse($this->rule()->isAssociationMemberRoleName('junta'));
    }

    public function test_the_configured_role_is_the_association_member_role(): void
    {
        $this->configure('junta');

        $this->assertTrue($this->rule()->isAssociationMemberRoleName('junta'));
        $this->assertFalse($this->rule()->isAssociationMemberRoleName('socio'));
    }

    public function test_the_comparison_is_exact(): void
    {
        $this->configure('junta');

        $this->assertFalse($this->rule()->isAssociationMemberRoleName('Junta'));
        $this->assertFalse($this->rule()->isAssociationMemberRoleName(' junta'));
    }

    public function test_no_role_is_the_association_member_role_when_the_setting_holds_a_base_role(): void
    {
        Log::spy();
        $this->configure('member');

        $this->assertFalse($this->rule()->isAssociationMemberRoleName('member'));
        $this->assertFalse($this->rule()->isAssociationMemberRoleName('socio'));
    }

    private function configure(string $roleName): void
    {
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, $roleName);
    }

    private function rule(): AssociationMemberRoleRule
    {
        return app(AssociationMemberRoleRule::class);
    }
}
