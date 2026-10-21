<?php

declare(strict_types=1);

namespace App\Infrastructure\Legal;

use App\Application\Legal\DTOs\LegalMailNoticeDTO;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;

final class LegalMailNoticeRegistry implements LegalMailNoticeRegistryInterface
{
    /**
     * @var array<string, LegalMailNoticeDTO>
     */
    private array $notices = [];

    public function register(LegalMailNoticeDTO $notice): void
    {
        $this->notices[$notice->key] = $notice;
    }

    public function all(): array
    {
        return array_values($this->notices);
    }

    public function find(string $key): ?LegalMailNoticeDTO
    {
        return $this->notices[$key] ?? null;
    }
}
