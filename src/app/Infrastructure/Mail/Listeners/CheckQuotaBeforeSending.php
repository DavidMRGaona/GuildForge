<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Listeners;

use App\Application\Mail\Services\EmailQuotaServiceInterface;
use Illuminate\Mail\Events\MessageSending;

final readonly class CheckQuotaBeforeSending
{
    public function __construct(
        private EmailQuotaServiceInterface $quotaService,
    ) {}

    public function handle(MessageSending $event): bool
    {
        return $this->quotaService->canSendEmail();
    }
}
