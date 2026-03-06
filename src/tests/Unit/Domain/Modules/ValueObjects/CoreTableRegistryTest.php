<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\ValueObjects\CoreTableRegistry;
use PHPUnit\Framework\TestCase;

final class CoreTableRegistryTest extends TestCase
{
    public function test_it_returns_all_core_tables(): void
    {
        $registry = new CoreTableRegistry;

        $tables = $registry->all();

        $this->assertCount(32, $tables);
    }

    public function test_it_identifies_core_table(): void
    {
        $registry = new CoreTableRegistry;

        $this->assertTrue($registry->isCore('users'));
    }

    public function test_it_identifies_non_core_table(): void
    {
        $registry = new CoreTableRegistry;

        $this->assertFalse($registry->isCore('gametables_games'));
    }

    public function test_module_prefixed_tables_are_not_core(): void
    {
        $registry = new CoreTableRegistry;

        $this->assertFalse($registry->isCore('bookings_rooms'));
    }
}
