<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\Enums;

use App\Domain\Modules\Enums\ModuleStatus;
use PHPUnit\Framework\TestCase;

final class ModuleStatusTest extends TestCase
{
    public function test_it_has_disabled_status(): void
    {
        $this->assertContains(
            ModuleStatus::Disabled,
            ModuleStatus::cases()
        );
    }

    public function test_it_has_enabled_status(): void
    {
        $this->assertContains(
            ModuleStatus::Enabled,
            ModuleStatus::cases()
        );
    }

    public function test_disabled_has_correct_value(): void
    {
        $this->assertEquals('disabled', ModuleStatus::Disabled->value);
    }

    public function test_enabled_has_correct_value(): void
    {
        $this->assertEquals('enabled', ModuleStatus::Enabled->value);
    }

    public function test_it_can_create_from_string_value(): void
    {
        $status = ModuleStatus::from('enabled');

        $this->assertEquals(ModuleStatus::Enabled, $status);
    }

    public function test_it_has_exactly_two_cases(): void
    {
        $this->assertCount(2, ModuleStatus::cases());
    }
}
