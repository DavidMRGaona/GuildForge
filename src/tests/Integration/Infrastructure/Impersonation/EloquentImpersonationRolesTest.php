<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Impersonation;

use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The roles a view can be made of, as the dialog, the throwaway account, the bands and the log
 * name them.
 */
final class EloquentImpersonationRolesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_role_without_display_name_is_named_by_its_name(): void
    {
        $role = RoleModel::create(['name' => 'unnamed-role', 'display_name' => '  ']);

        $found = app(ImpersonationRolesInterface::class)->find([(string) $role->id]);

        $this->assertSame(['unnamed-role'], array_map(static fn (ViewedRoleDTO $dto): string => $dto->displayName, $found));
        $this->assertContains('unnamed-role', $this->offeredDisplayNames());
    }

    public function test_a_role_with_display_name_keeps_it(): void
    {
        $role = RoleModel::create(['name' => 'named-role', 'display_name' => 'Rol con nombre']);

        $found = app(ImpersonationRolesInterface::class)->find([(string) $role->id]);

        $this->assertSame('Rol con nombre', $found[0]->displayName ?? null);
        $this->assertSame('named-role', $found[0]->name ?? null);
    }

    /**
     * @return list<string>
     */
    private function offeredDisplayNames(): array
    {
        return array_map(
            static fn (ViewedRoleDTO $dto): string => $dto->displayName,
            app(ImpersonationRolesInterface::class)->offered(),
        );
    }
}
