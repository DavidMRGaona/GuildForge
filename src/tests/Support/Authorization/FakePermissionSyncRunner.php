<?php

declare(strict_types=1);

namespace Tests\Support\Authorization;

use App\Application\Authorization\Services\PermissionSyncRunnerInterface;

/**
 * Bound for every test by Tests\TestCase: the real runner starts a PHP process, which
 * would not see the test database and would slow down every test that enables a module.
 */
final class FakePermissionSyncRunner implements PermissionSyncRunnerInterface
{
    public int $runs = 0;

    public function sync(): void
    {
        $this->runs++;
    }
}
