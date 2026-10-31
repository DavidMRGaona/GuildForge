<?php

declare(strict_types=1);

namespace Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Tests\Support\Testing\XdebugDevelopModeGuard;

final class XdebugDevelopModeGuardTest extends TestCase
{
    public function test_it_stops_the_run_in_develop_mode(): void
    {
        $problem = XdebugDevelopModeGuard::problem(['develop', 'debug', 'coverage']);

        $this->assertNotNull($problem);
        $this->assertStringContainsString('XDEBUG_MODE=off', $problem);
    }

    public function test_it_lets_the_other_modes_run(): void
    {
        $this->assertNull(XdebugDevelopModeGuard::problem([]));
        $this->assertNull(XdebugDevelopModeGuard::problem(['off']));
        $this->assertNull(XdebugDevelopModeGuard::problem(['debug', 'coverage']));
    }

    public function test_this_run_is_not_in_develop_mode(): void
    {
        $this->assertNotContains('develop', XdebugDevelopModeGuard::activeModes());
    }
}
