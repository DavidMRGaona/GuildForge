<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class KnowledgeSyncReportDTO
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $inserted,
        public int $updated,
        public int $deactivated,
        public array $errors,
    ) {}
}
