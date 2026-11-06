<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\DTOs\KnowledgeIndexResultDTO;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * Used when no search engine is configured: the database search needs no index.
 */
final readonly class NullKnowledgeIndexer implements KnowledgeIndexerInterface
{
    public function reindex(): KnowledgeIndexResultDTO
    {
        return new KnowledgeIndexResultDTO(0, 0, null);
    }

    public function refresh(GuideId $id): void {}

    public function forget(GuideId $id): void {}
}
