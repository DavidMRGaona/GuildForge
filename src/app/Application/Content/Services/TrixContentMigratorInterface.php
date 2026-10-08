<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

use App\Application\Content\DTOs\TrixContentMigrationReportDTO;

/**
 * Converts the core's stored rich text (articles, events, about and legal pages) once,
 * before anyone edits it with the TipTap editor. Modules convert their own tables with
 * TrixHtmlConverterInterface.
 */
interface TrixContentMigratorInterface
{
    /**
     * Converts every stored value; with $dryRun nothing is written. Before writing, the
     * original values are saved to a JSON backup (its path is in the report).
     */
    public function migrate(bool $dryRun = false): TrixContentMigrationReportDTO;

    /**
     * Puts back the original values of a backup, except those edited since the conversion.
     *
     * @return int Values restored
     */
    public function restore(string $backupPath): int;
}
