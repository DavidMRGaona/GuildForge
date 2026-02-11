<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

final readonly class UpdateMailConfigurationDTO
{
    public function __construct(
        public bool $enabled,
        public string $driver,
        public string $fromAddress,
        public string $fromName,
        public ?string $smtpHost = null,
        public ?int $smtpPort = null,
        public ?string $smtpUsername = null,
        public ?string $smtpPassword = null,
        public ?string $smtpEncryption = null,
        public ?int $smtpTimeout = null,
        public ?string $sesRegion = null,
        public ?string $sesAccessKeyId = null,
        public ?string $sesSecretAccessKey = null,
        public ?string $resendApiKey = null,
        public ?int $quotaDailyLimit = null,
        public ?int $quotaMonthlyLimit = null,
        public ?int $quotaWarningThreshold = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['mail_enabled'] ?? false),
            driver: (string) ($data['mail_driver'] ?? 'smtp'),
            fromAddress: (string) ($data['mail_from_address'] ?? ''),
            fromName: (string) ($data['mail_from_name'] ?? ''),
            smtpHost: isset($data['mail_smtp_host']) ? (string) $data['mail_smtp_host'] : null,
            smtpPort: isset($data['mail_smtp_port']) ? (int) $data['mail_smtp_port'] : null,
            smtpUsername: isset($data['mail_smtp_username']) ? (string) $data['mail_smtp_username'] : null,
            smtpPassword: isset($data['mail_smtp_password']) ? (string) $data['mail_smtp_password'] : null,
            smtpEncryption: isset($data['mail_smtp_encryption']) ? (string) $data['mail_smtp_encryption'] : null,
            smtpTimeout: isset($data['mail_smtp_timeout']) ? (int) $data['mail_smtp_timeout'] : null,
            sesRegion: isset($data['mail_ses_region']) ? (string) $data['mail_ses_region'] : null,
            sesAccessKeyId: isset($data['mail_ses_access_key_id']) ? (string) $data['mail_ses_access_key_id'] : null,
            sesSecretAccessKey: isset($data['mail_ses_secret_access_key']) ? (string) $data['mail_ses_secret_access_key'] : null,
            resendApiKey: isset($data['mail_resend_api_key']) ? (string) $data['mail_resend_api_key'] : null,
            quotaDailyLimit: isset($data['mail_quota_daily_limit']) ? (int) $data['mail_quota_daily_limit'] : null,
            quotaMonthlyLimit: isset($data['mail_quota_monthly_limit']) ? (int) $data['mail_quota_monthly_limit'] : null,
            quotaWarningThreshold: isset($data['mail_quota_warning_threshold']) ? (int) $data['mail_quota_warning_threshold'] : null,
        );
    }
}
