<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Impersonation;

use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImpersonationSessionStateTest extends TestCase
{
    public function test_a_role_view_round_trips_through_the_session(): void
    {
        $roleA = (string) Str::uuid();
        $roleB = (string) Str::uuid();
        $state = new ImpersonationSessionState((string) Str::uuid(), (string) Str::uuid(), ImpersonationMode::ReadOnly, (string) Str::uuid(), [$roleA, $roleB]);

        $restored = ImpersonationSessionState::fromSession($state->toArray());

        $this->assertNotNull($restored);
        $this->assertSame([$roleA, $roleB], $restored->roleIds);
        $this->assertTrue($restored->isRoleView());
    }

    public function test_a_state_stored_before_role_views_reads_as_an_account(): void
    {
        $restored = ImpersonationSessionState::fromSession([
            'target_id' => (string) Str::uuid(),
            'log_id' => (string) Str::uuid(),
            'mode' => 'read_only',
            'impersonator_id' => (string) Str::uuid(),
        ]);

        $this->assertNotNull($restored);
        $this->assertNull($restored->roleIds);
        $this->assertFalse($restored->isRoleView());
        $this->assertArrayNotHasKey('role_ids', $restored->toArray());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function malformedRoleIds(): array
    {
        return [
            'empty list' => [[]],
            'not a uuid' => [['x']],
            'not a list' => ['abc'],
        ];
    }

    #[DataProvider('malformedRoleIds')]
    public function test_malformed_role_ids_drop_the_state(mixed $roleIds): void
    {
        $this->assertNull(ImpersonationSessionState::fromSession([
            'target_id' => (string) Str::uuid(),
            'log_id' => (string) Str::uuid(),
            'mode' => 'read_only',
            'impersonator_id' => (string) Str::uuid(),
            'role_ids' => $roleIds,
        ]));
    }
}
