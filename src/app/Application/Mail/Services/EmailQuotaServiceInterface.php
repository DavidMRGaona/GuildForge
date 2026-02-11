<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\Response\QuotaStatusResponseDTO;

interface EmailQuotaServiceInterface
{
    public function canSendEmail(): bool;

    public function getQuotaStatus(): QuotaStatusResponseDTO;
}
