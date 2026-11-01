<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\Response\KnowledgeSearchHitDTO;
use App\Domain\Knowledge\Entities\Guide;

interface KnowledgeSearchInterface
{
    /**
     * Searches only among the given candidates (the guides the user may see, all in one locale and audience).
     *
     * @param  list<Guide>  $candidates
     * @return list<KnowledgeSearchHitDTO>
     */
    public function search(string $query, array $candidates, int $limit): array;
}
