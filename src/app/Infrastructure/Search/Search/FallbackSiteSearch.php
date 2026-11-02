<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SiteSearchInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Uses the primary search and, when it fails for any reason, logs a warning and answers with the fallback.
 */
final readonly class FallbackSiteSearch implements SiteSearchInterface
{
    public function __construct(
        private SiteSearchInterface $primary,
        private SiteSearchInterface $fallback,
        private LoggerInterface $logger,
    ) {}

    public function search(SiteSearchQueryDTO $query): SiteSearchResultDTO
    {
        try {
            return $this->primary->search($query);
        } catch (Throwable $e) {
            $this->logger->warning('[SiteSearch] Search engine failed, using the database fallback: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->fallback->search($query);
        }
    }
}
