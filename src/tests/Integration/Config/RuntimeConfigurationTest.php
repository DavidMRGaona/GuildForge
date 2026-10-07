<?php

declare(strict_types=1);

namespace Tests\Integration\Config;

use Tests\TestCase;

final class RuntimeConfigurationTest extends TestCase
{
    /**
     * Sessions stored before the upgrade (and after a rollback) use PHP
     * serialization; "json" would end every open session.
     */
    public function test_sessions_keep_php_serialization(): void
    {
        $this->assertSame('php', config('session.serialization'));
    }

    public function test_the_daily_log_names_its_retention_max_files(): void
    {
        $channel = config('logging.channels.daily');

        $this->assertIsArray($channel);
        $this->assertArrayHasKey('max_files', $channel);
        $this->assertArrayNotHasKey('days', $channel);
    }
}
