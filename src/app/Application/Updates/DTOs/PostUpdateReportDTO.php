<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

/**
 * What module:finish-update applied, passed from the child process to the updater
 * as a single prefixed JSON line on standard output.
 */
final readonly class PostUpdateReportDTO
{
    private const string OUTPUT_PREFIX = 'module-update-report:';

    /**
     * @param  array<string>  $migrations  Migrations applied by this run (not the ones that had already run)
     * @param  array<string>  $seeders  Seeder classes executed (module seeders are idempotent and all run)
     */
    public function __construct(
        public array $migrations,
        public array $seeders,
    ) {}

    public function toOutputLine(): string
    {
        return self::OUTPUT_PREFIX.json_encode(
            ['migrations' => $this->migrations, 'seeders' => $this->seeders],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * Find the report line in a command's output; null when there is none.
     */
    public static function fromOutput(string $output): ?self
    {
        foreach (array_reverse(preg_split('/\R/', $output) ?: []) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, self::OUTPUT_PREFIX)) {
                continue;
            }

            $data = json_decode(substr($line, strlen(self::OUTPUT_PREFIX)), true);

            if (! is_array($data)) {
                return null;
            }

            return new self(
                array_values(array_map(strval(...), (array) ($data['migrations'] ?? []))),
                array_values(array_map(strval(...), (array) ($data['seeders'] ?? []))),
            );
        }

        return null;
    }
}
