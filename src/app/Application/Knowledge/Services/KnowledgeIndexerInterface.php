<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\KnowledgeIndexResultDTO;
use App\Domain\Knowledge\ValueObjects\GuideId;

interface KnowledgeIndexerInterface
{
    /**
     * Pushes the index settings and the stored guides to the search index. Never throws: failures come back in the result.
     */
    public function reindex(): KnowledgeIndexResultDTO;

    /**
     * Brings one guide in line with the table: active rows are pushed, inactive ones removed.
     * Never throws: a failure is reported and the next reindex catches up.
     */
    public function refresh(GuideId $id): void;

    /**
     * Removes a guide from the index before its rows are deleted. Never throws.
     */
    public function forget(GuideId $id): void;
}
