<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation\SafetyNet;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\Events\TransactionRolledBack;
use LogicException;

/**
 * A transaction of the default connection that is never committed: whatever runs inside it is
 * seen by the request and undone by rollBack(). Transactions opened inside become savepoints,
 * so DB::afterCommit() callbacks never run.
 *
 * PostgreSQL aborts the whole transaction when one statement fails, and every later statement
 * would fail too (the error page, a repository that catches a unique violation and carries
 * on). So, on PostgreSQL, each statement runs behind a savepoint of its own: released once it
 * succeeds, rolled back to before the next statement when it did not. A later statement then
 * behaves as it would without the transaction.
 *
 * A commit that would end this transaction throws: it would persist what it holds.
 */
final class RolledBackTransaction
{
    private const string STATEMENT_SAVEPOINT = 'impersonation_statement_';

    /** Transaction level before this one began; null while it is not open */
    private ?int $baseLevel = null;

    private ?Connection $connection = null;

    /** @var list<array{name: string, level: int}> Statement savepoints not released yet */
    private array $statements = [];

    private int $counter = 0;

    /** @var array<int, true> Connections whose statements are watched, by object id */
    private array $watched = [];

    private bool $listening = false;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Dispatcher $events,
    ) {}

    public function isOpen(): bool
    {
        return $this->baseLevel !== null;
    }

    public function begin(): void
    {
        if ($this->isOpen()) {
            return;
        }

        $connection = $this->database->connection();
        $this->listen();
        $this->watch($connection);

        $level = $connection->transactionLevel();
        $connection->beginTransaction();

        $this->connection = $connection;
        $this->baseLevel = $level;
        $this->statements = [];
    }

    public function rollBack(): void
    {
        $connection = $this->connection;
        $level = $this->baseLevel;

        $this->baseLevel = null;
        $this->connection = null;
        $this->statements = [];
        if ($connection instanceof Connection && $level !== null) {
            $connection->rollBack($level);
        }
    }

    private function listen(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        $this->events->listen(QueryExecuted::class, function (QueryExecuted $event): void {
            $this->statementSucceeded($event->connection);
        });
        $this->events->listen(TransactionCommitting::class, function (TransactionCommitting $event): void {
            if ($this->isOpen() && $event->connection === $this->connection) {
                throw new LogicException('A commit would persist the impersonation\'s rolled back transaction.');
            }
        });
        $this->events->listen(TransactionRolledBack::class, function (TransactionRolledBack $event): void {
            $this->forgetSavepointsAbove($event->connection);
        });
        $this->events->listen(TransactionCommitted::class, function (TransactionCommitted $event): void {
            $this->forgetSavepointsAbove($event->connection);
        });
    }

    private function watch(Connection $connection): void
    {
        $id = spl_object_id($connection);
        if (isset($this->watched[$id]) || $connection->getDriverName() !== 'pgsql') {
            return;
        }

        $this->watched[$id] = true;
        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            $this->beforeStatement($connection);
        });
    }

    private function beforeStatement(Connection $connection): void
    {
        if (! $this->isOpen() || $connection !== $this->connection || $connection->pretending()) {
            return;
        }

        $pdo = $connection->getPdo();

        // Still there: the previous statement failed and left the transaction aborted
        $failed = array_pop($this->statements);
        if ($failed !== null) {
            $pdo->exec('ROLLBACK TO SAVEPOINT '.$failed['name']);
            $pdo->exec('RELEASE SAVEPOINT '.$failed['name']);
        }

        $name = self::STATEMENT_SAVEPOINT.(++$this->counter);
        $pdo->exec('SAVEPOINT '.$name);
        $this->statements[] = ['name' => $name, 'level' => $connection->transactionLevel()];
    }

    private function statementSucceeded(Connection $connection): void
    {
        if (! $this->isOpen() || $connection !== $this->connection) {
            return;
        }

        $statement = array_pop($this->statements);
        if ($statement !== null) {
            $connection->getPdo()->exec('RELEASE SAVEPOINT '.$statement['name']);
        }
    }

    /**
     * Rolling back to (or releasing) one of Laravel's savepoints drops every savepoint made
     * after it, ours included.
     */
    private function forgetSavepointsAbove(Connection $connection): void
    {
        if (! $this->isOpen() || $connection !== $this->connection) {
            return;
        }

        $level = $connection->transactionLevel();
        $this->statements = array_values(array_filter(
            $this->statements,
            static fn (array $statement): bool => $statement['level'] <= $level,
        ));
    }
}
