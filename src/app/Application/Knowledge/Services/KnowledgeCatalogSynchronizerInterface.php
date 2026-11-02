<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\KnowledgeSyncReportDTO;

interface KnowledgeCatalogSynchronizerInterface
{
    /**
     * Reads every guide file from the core and the enabled modules and brings the catalog in line.
     */
    public function sync(): KnowledgeSyncReportDTO;
}
