<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

final readonly class MailOptOutReportDTO
{
    /**
     * @param  list<MailCategoryOptOutDTO>  $categories  Optional categories, in registration order.
     * @param  int  $pausedAccounts  Active accounts with their optional mail paused right now.
     */
    public function __construct(
        public array $categories,
        public int $pausedAccounts,
    ) {}
}
