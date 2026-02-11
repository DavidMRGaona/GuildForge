<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\Response\MailStatisticsResponseDTO;

interface MailStatisticsServiceInterface
{
    public function getStats(): MailStatisticsResponseDTO;
}
