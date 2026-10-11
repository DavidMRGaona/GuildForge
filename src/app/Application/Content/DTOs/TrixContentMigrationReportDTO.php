<?php

declare(strict_types=1);

namespace App\Application\Content\DTOs;

final readonly class TrixContentMigrationReportDTO
{
    /**
     * @param  list<string>  $changed  "table.column#id" or "settings#key" of every value that changes
     */
    public function __construct(
        public int $checked,
        public array $changed,
        public ?string $backupPath,
    ) {
    }
}
