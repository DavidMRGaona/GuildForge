<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs\Response;

final readonly class MailConfigurationResponseDTO
{
    public function __construct(
        public bool $enabled,
        public string $driver,
        public string $fromAddress,
        public string $fromName,
        public ?string $smtpHost,
        public ?int $smtpPort,
        public ?string $smtpUsername,
        public bool $hasSmtpPassword,
        public ?string $smtpEncryption,
        public ?int $smtpTimeout,
        public ?string $sesRegion,
        public ?string $sesAccessKeyId,
        public bool $hasSesSecretAccessKey,
        public bool $hasResendApiKey,
        public ?int $quotaDailyLimit,
        public ?int $quotaMonthlyLimit,
        public ?int $quotaWarningThreshold,
    ) {}
}
