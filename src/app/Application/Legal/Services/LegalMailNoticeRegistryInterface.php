<?php

declare(strict_types=1);

namespace App\Application\Legal\Services;

use App\Application\Legal\DTOs\LegalMailNoticeDTO;

interface LegalMailNoticeRegistryInterface
{
    /**
     * Register a notice; a repeated key replaces the previous one.
     */
    public function register(LegalMailNoticeDTO $notice): void;

    /**
     * @return list<LegalMailNoticeDTO> In registration order.
     */
    public function all(): array;

    public function find(string $key): ?LegalMailNoticeDTO;
}
