<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use App\Domain\Modules\ValueObjects\CoreTableRegistry;
use App\Infrastructure\Modules\Services\ModuleSchemaGuard;
use PHPUnit\Framework\TestCase;

final class ModuleSchemaGuardTest extends TestCase
{
    private ModuleSchemaGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new ModuleSchemaGuard(new CoreTableRegistry);
    }

    public function test_it_is_inactive_by_default(): void
    {
        $this->expectNotToPerformAssertions();

        // Act - should not throw
        $this->guard->assertPermitted('create', 'users');
    }

    public function test_it_allows_operations_when_inactive(): void
    {
        $this->expectNotToPerformAssertions();

        // Act - should not throw even for a core table
        $this->guard->assertPermitted('table', 'users');
        $this->guard->assertPermitted('create', 'events');
        $this->guard->assertPermitted('table', 'articles');
    }

    public function test_it_blocks_ddl_on_core_table_when_active(): void
    {
        // Assert
        $this->expectException(ModuleMigrationViolationException::class);

        // Act
        $this->guard->protect('game-tables', function (): void {
            $this->guard->assertPermitted('table', 'users');
        });
    }

    public function test_it_allows_ddl_on_module_table_when_active(): void
    {
        // Act
        $completed = false;
        $this->guard->protect('game-tables', function () use (&$completed): void {
            $this->guard->assertPermitted('create', 'gametables_games');

            $completed = true;
        });

        // Assert
        $this->assertTrue($completed);
    }

    public function test_it_allows_ddl_on_module_table_with_underscore_prefix_when_active(): void
    {
        // Act
        $completed = false;
        $this->guard->protect('game-tables', function () use (&$completed): void {
            $this->guard->assertPermitted('create', 'game_tables_sessions');

            $completed = true;
        });

        // Assert
        $this->assertTrue($completed);
    }

    public function test_it_deactivates_after_protect_completes(): void
    {
        // Arrange - run protect to completion
        $this->guard->protect('game-tables', function (): void {
            // do nothing
        });

        $this->expectNotToPerformAssertions();

        // Act - should not throw, guard is inactive again
        $this->guard->assertPermitted('table', 'users');
    }

    public function test_it_deactivates_even_on_exception(): void
    {
        // Arrange - run protect with a callback that throws
        try {
            $this->guard->protect('game-tables', function (): void {
                throw new \RuntimeException('Callback failure');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->expectNotToPerformAssertions();

        // Act - should not throw, guard is inactive again
        $this->guard->assertPermitted('table', 'users');
    }

    public function test_it_returns_callback_result(): void
    {
        // Arrange
        $value = new \stdClass;

        // Act
        $result = $this->guard->protect('game-tables', fn (): \stdClass => $value);

        // Assert
        $this->assertSame($value, $result);
    }
}
