<?php

declare(strict_types=1);

namespace App\Domain\Mail\Repositories;

use App\Domain\Mail\Entities\EmailLog;
use App\Domain\Mail\Enums\EmailStatus;
use Illuminate\Support\Collection;

interface EmailLogRepositoryInterface
{
    public function save(EmailLog $emailLog): void;

    /**
     * @return Collection<int, EmailLog>
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): Collection;

    public function countByStatusSince(EmailStatus $status, \DateTimeInterface $since): int;

    /**
     * @return Collection<int, EmailLog>
     */
    public function getRecentFailed(int $limit = 10): Collection;

    public function updateStatusByMessageId(string $messageId, EmailStatus $status): void;
}
