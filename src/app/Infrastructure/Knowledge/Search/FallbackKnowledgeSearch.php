<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Search;

use App\Application\Knowledge\Services\KnowledgeSearchInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Uses the primary search and, when it fails for any reason, logs a warning and answers with the fallback.
 */
final readonly class FallbackKnowledgeSearch implements KnowledgeSearchInterface
{
    public function __construct(
        private KnowledgeSearchInterface $primary,
        private KnowledgeSearchInterface $fallback,
        private LoggerInterface $logger,
    ) {}

    public function search(string $query, array $candidates, int $limit): array
    {
        try {
            return $this->primary->search($query, $candidates, $limit);
        } catch (Throwable $e) {
            $this->logger->warning('[KnowledgeSearch] Search engine failed, using the database fallback: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->fallback->search($query, $candidates, $limit);
        }
    }
}
