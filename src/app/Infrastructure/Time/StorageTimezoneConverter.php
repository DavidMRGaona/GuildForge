<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use App\Application\Services\StorageTimezoneConverterInterface;
use Closure;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use LogicException;

/**
 * Converts the stored instants with PostgreSQL's AT TIME ZONE, which reads each wall time in the
 * source zone (the ambiguous autumn hour as standard time, the skipped spring hour one hour later)
 * and writes it in the target zone. There is no portable SQL for this, so other drivers are refused.
 */
final readonly class StorageTimezoneConverter implements StorageTimezoneConverterInterface
{
    /** Serialises two runs started at once: the second waits, then sees the first one's marker. */
    private const int ADVISORY_LOCK_KEY = 2026102508;

    public function __construct(
        private DatabaseManager $db,
        private TimestampColumnFinder $finder,
    ) {}

    public function supportsConnection(): bool
    {
        return $this->db->connection()->getDriverName() === 'pgsql';
    }

    public function countValues(): array
    {
        $connection = $this->db->connection();
        $counts = [];

        foreach ($this->finder->find() as ['table' => $table, 'column' => $column]) {
            $counts[$table.'.'.$column] = $connection->table($table)->whereNotNull($column)->count();
        }

        return $counts;
    }

    public function convert(string $from, string $to, ?Closure $afterLock = null): array
    {
        foreach ([$from, $to] as $zone) {
            if (! in_array($zone, StorageTimezoneResolver::ALLOWED, true)) {
                throw new InvalidArgumentException("Unsupported storage timezone: {$zone}");
            }
        }

        if ($from === $to) {
            throw new InvalidArgumentException("Nothing to convert: both zones are {$from}");
        }

        $connection = $this->db->connection();

        if (! $this->supportsConnection()) {
            throw new LogicException("Stored instants can only be converted on PostgreSQL, not on {$connection->getDriverName()}");
        }

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The conversion must run inside a transaction that also writes the storage timezone marker');
        }

        $connection->select('select pg_advisory_xact_lock('.self::ADVISORY_LOCK_KEY.')');

        if ($afterLock instanceof Closure) {
            $afterLock();
        }

        $grammar = $connection->getQueryGrammar();
        $counts = [];

        foreach ($this->finder->find() as ['table' => $table, 'column' => $column]) {
            $wrapped = $grammar->wrap($column);

            $counts[$table.'.'.$column] = $connection->update(
                "update {$grammar->wrapTable($table)} set {$wrapped} = ({$wrapped} at time zone ?) at time zone ? where {$wrapped} is not null",
                [$from, $to],
            );
        }

        return $counts;
    }
}
