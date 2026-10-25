<?php

declare(strict_types=1);

namespace Tests\Support\Time;

use App\Application\Services\StorageTimezoneConverterInterface;
use Closure;
use Illuminate\Support\Facades\DB;

/** Stands in for the PostgreSQL converter, so the conversion command runs past the driver check on SQLite. */
final class FakeStorageTimezoneConverter implements StorageTimezoneConverterInterface
{
    /** @var list<array{string, string, int}> from, to and the transaction level of each call */
    public array $calls = [];

    /** @param  array<string, int>  $counts */
    public function __construct(
        private readonly array $counts,
        private readonly ?Closure $onConvert,
        private readonly ?Closure $onLock = null,
    ) {}

    public function supportsConnection(): bool
    {
        return true;
    }

    public function countValues(): array
    {
        return $this->counts;
    }

    public function convert(string $from, string $to, ?Closure $afterLock = null): array
    {
        // What another run committing while this one waited for the lock would have done
        if ($this->onLock instanceof Closure) {
            ($this->onLock)();
        }

        if ($afterLock instanceof Closure) {
            $afterLock();
        }

        // Relative to the test's own transaction (RefreshDatabase): 1 means inside the command's
        $this->calls[] = [$from, $to, DB::transactionLevel() - 1];

        if ($this->onConvert instanceof Closure) {
            ($this->onConvert)();
        }

        return $this->counts;
    }
}
