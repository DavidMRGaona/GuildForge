<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\KnowledgeIndexResultDTO;

interface KnowledgeIndexerInterface
{
    /**
     * Pushes the index settings and the stored guides to the search index. Never throws: failures come back in the result.
     */
    public function reindex(): KnowledgeIndexResultDTO;
}
