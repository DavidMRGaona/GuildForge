<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\MailOptOutReportDTO;

/**
 * Counts of who turned off the optional mail categories and who paused them, for the panel's
 * mail statistics. Anonymous: never names or addresses. Accounts that are anonymized or
 * deactivated count nowhere, neither as opt-outs nor as people who could receive the mail.
 */
interface MailCategoryOptOutStatisticsInterface
{
    public function report(): MailOptOutReportDTO;
}
