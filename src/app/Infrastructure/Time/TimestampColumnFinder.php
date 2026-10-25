<?php

declare(strict_types=1);

namespace App\Infrastructure\Time;

use Illuminate\Database\DatabaseManager;

/**
 * Lists the columns that hold instants as wall times without a zone, in every table of the
 * current schema (core and modules alike). Dates, times, JSON and zone-aware types are not
 * instants written in the storage zone, so they are never listed.
 */
final readonly class TimestampColumnFinder
{
    /**
     * Timestamps with a database default (useCurrent()) that the app always writes from PHP, so
     * they hold storage-zone wall times like any other column: Laravel's failer sets failed_at
     * itself. A column the database fills is not in the storage zone, so the conversion refuses
     * to run while any defaulted column is missing from this list.
     *
     * @var list<array{table: string, column: string}>
     */
    public const array APP_WRITTEN_DESPITE_DEFAULT = [
        ['table' => 'failed_jobs', 'column' => 'failed_at'],
    ];

    /** Type names per driver: PostgreSQL "timestamp" (without time zone), SQLite and MySQL "datetime". */
    private const array INSTANT_TYPES = ['timestamp', 'datetime', 'timestamp without time zone'];

    public function __construct(
        private DatabaseManager $db,
    ) {}

    /**
     * @return list<array{table: string, column: string}> Sorted by table, then column
     */
    public function find(): array
    {
        return array_map(
            static fn (array $column): array => ['table' => $column['table'], 'column' => $column['column']],
            $this->timestampColumns(),
        );
    }

    /**
     * Columns that carry their own zone (PostgreSQL timestamptz): they are never converted, but an
     * operator should know they exist.
     *
     * @return list<array{table: string, column: string}> Sorted by table, then column
     */
    public function zoneAware(): array
    {
        $schema = $this->db->connection()->getSchemaBuilder();
        $current = $schema->getCurrentSchemaName();
        $columns = [];

        foreach ($schema->getTables($current) as $table) {
            $name = (string) $table['name'];
            $reference = $current !== null ? $current.'.'.$name : $name;

            foreach ($schema->getColumns($reference) as $column) {
                $typeName = strtolower(trim((string) $column['type_name']));

                if ($typeName === 'timestamptz' || str_contains(strtolower((string) $column['type']), 'with time zone')) {
                    $columns[] = ['table' => $name, 'column' => (string) $column['name']];
                }
            }
        }

        usort($columns, static fn (array $a, array $b): int => [$a['table'], $a['column']] <=> [$b['table'], $b['column']]);

        return $columns;
    }

    /**
     * Timestamp columns with a database default (CURRENT_TIMESTAMP or any other), allowlisted or not.
     *
     * @return list<array{table: string, column: string}>
     */
    public function databaseDefaultedColumns(): array
    {
        $columns = [];

        foreach ($this->timestampColumns() as $column) {
            if ($column['defaulted']) {
                $columns[] = ['table' => $column['table'], 'column' => $column['column']];
            }
        }

        return $columns;
    }

    /**
     * @return list<array{table: string, column: string, defaulted: bool}>
     */
    private function timestampColumns(): array
    {
        $schema = $this->db->connection()->getSchemaBuilder();
        $current = $schema->getCurrentSchemaName();
        $columns = [];

        foreach ($schema->getTables($current) as $table) {
            $name = (string) $table['name'];
            $reference = $current !== null ? $current.'.'.$name : $name;

            foreach ($schema->getColumns($reference) as $column) {
                if (! $this->isInstant((string) $column['type_name'], (string) $column['type'])) {
                    continue;
                }

                $columns[] = [
                    'table' => $name,
                    'column' => (string) $column['name'],
                    'defaulted' => $this->hasDefault($column['default']),
                ];
            }
        }

        usort($columns, static fn (array $a, array $b): int => [$a['table'], $a['column']] <=> [$b['table'], $b['column']]);

        return $columns;
    }

    private function isInstant(string $typeName, string $type): bool
    {
        // "timestamp(0) without time zone" -> "timestamp without time zone"
        $withoutPrecision = (string) preg_replace('/\s*\(\d+\)/', '', $type);

        return in_array(strtolower(trim($typeName)), self::INSTANT_TYPES, true)
            || in_array(strtolower(trim($withoutPrecision)), self::INSTANT_TYPES, true);
    }

    private function hasDefault(mixed $default): bool
    {
        if ($default === null) {
            return false;
        }

        // PostgreSQL may spell an explicit null default as "NULL::timestamp without time zone"
        return ! str_starts_with(strtoupper(trim((string) $default)), 'NULL');
    }
}
