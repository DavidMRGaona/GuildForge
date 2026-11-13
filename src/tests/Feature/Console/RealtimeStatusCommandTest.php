<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Tests\Support\Realtime\EnablesRealtime;
use Tests\TestCase;

final class RealtimeStatusCommandTest extends TestCase
{
    use EnablesRealtime;

    public function test_it_succeeds_and_names_the_browser_origin_when_real_time_is_on(): void
    {
        $this->enableRealtime();

        $this->artisanCommand('realtime:status')
            ->expectsOutputToContain('wss://example.test')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_real_time_is_off(): void
    {
        $this->enableRealtime(['broadcasting.default' => 'log']);

        $this->artisanCommand('realtime:status')
            ->expectsOutputToContain('Real time is off')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_a_setting_is_missing(): void
    {
        $this->enableRealtime(['broadcasting.connections.reverb.secret' => '']);

        $this->artisanCommand('realtime:status')
            ->expectsOutputToContain('Real time is off')
            ->assertExitCode(1);
    }
}
