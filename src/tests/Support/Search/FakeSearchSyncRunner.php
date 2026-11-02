<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Application\Search\Services\SearchSyncRunnerInterface;

/**
 * Bound for every test by Tests\TestCase: the real runner starts a PHP process, which
 * would not see the test database and would slow down every test that enables a module.
 */
final class FakeSearchSyncRunner implements SearchSyncRunnerInterface
{
    /** @var list<string|null> */
    public array $runs = [];

    public function __construct(private readonly bool $succeeds = true) {}

    public function sync(?string $type = null): bool
    {
        $this->runs[] = $type;

        return $this->succeeds;
    }
}
