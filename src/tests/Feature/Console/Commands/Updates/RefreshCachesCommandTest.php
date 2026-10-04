<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use Tests\TestCase;

final class RefreshCachesCommandTest extends TestCase
{
    public function test_it_succeeds_without_touching_caches_under_unit_tests(): void
    {
        $this->artisan('module:refresh-caches')->assertExitCode(0);
    }
}
