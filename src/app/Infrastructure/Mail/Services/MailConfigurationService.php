<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Services;

use App\Application\Mail\DTOs\Response\MailConfigurationResponseDTO;
use App\Application\Mail\DTOs\UpdateMailConfigurationDTO;
use App\Application\Mail\Services\MailConfigurationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Mail\Enums\MailDriver;
use App\Domain\Mail\Events\MailConfigurationUpdated;
use Illuminate\Support\Facades\Config;

final class MailConfigurationService implements MailConfigurationServiceInterface
{
    public function __construct(
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function getConfiguration(): MailConfigurationResponseDTO
    {
        return new MailConfigurationResponseDTO(
            enabled: $this->isMailEnabled(),
            driver: (string) $this->settingsService->get('mail_driver', 'smtp'),
            fromAddress: (string) $this->settingsService->get('mail_from_address', ''),
            fromName: (string) $this->settingsService->get('mail_from_name', ''),
            smtpHost: $this->getStringOrNull('mail_smtp_host'),
            smtpPort: $this->getIntOrNull('mail_smtp_port'),
            smtpUsername: $this->getStringOrNull('mail_smtp_username'),
            hasSmtpPassword: $this->settingsService->get('mail_smtp_password', '') !== '',
            smtpEncryption: $this->getStringOrNull('mail_smtp_encryption'),
            smtpTimeout: $this->getIntOrNull('mail_smtp_timeout'),
            sesRegion: $this->getStringOrNull('mail_ses_region'),
            sesAccessKeyId: $this->getStringOrNull('mail_ses_access_key_id'),
            hasSesSecretAccessKey: $this->settingsService->get('mail_ses_secret_access_key', '') !== '',
            hasResendApiKey: $this->settingsService->get('mail_resend_api_key', '') !== '',
            quotaDailyLimit: $this->getIntOrNull('mail_quota_daily_limit'),
            quotaMonthlyLimit: $this->getIntOrNull('mail_quota_monthly_limit'),
            quotaWarningThreshold: $this->getIntOrNull('mail_quota_warning_threshold'),
        );
    }

    public function updateConfiguration(UpdateMailConfigurationDTO $dto): void
    {
        $this->settingsService->set('mail_enabled', $dto->enabled ? '1' : '0');
        $this->settingsService->set('mail_driver', $dto->driver);
        $this->settingsService->set('mail_from_address', $dto->fromAddress);
        $this->settingsService->set('mail_from_name', $dto->fromName);

        if ($dto->smtpHost !== null) {
            $this->settingsService->set('mail_smtp_host', $dto->smtpHost);
        }
        if ($dto->smtpPort !== null) {
            $this->settingsService->set('mail_smtp_port', (string) $dto->smtpPort);
        }
        if ($dto->smtpUsername !== null) {
            $this->settingsService->set('mail_smtp_username', $dto->smtpUsername);
        }
        if ($dto->smtpPassword !== null && $dto->smtpPassword !== '') {
            $this->settingsService->setEncrypted('mail_smtp_password', $dto->smtpPassword);
        }
        if ($dto->smtpEncryption !== null) {
            $this->settingsService->set('mail_smtp_encryption', $dto->smtpEncryption);
        }
        if ($dto->smtpTimeout !== null) {
            $this->settingsService->set('mail_smtp_timeout', (string) $dto->smtpTimeout);
        }

        if ($dto->sesRegion !== null) {
            $this->settingsService->set('mail_ses_region', $dto->sesRegion);
        }
        if ($dto->sesAccessKeyId !== null) {
            $this->settingsService->set('mail_ses_access_key_id', $dto->sesAccessKeyId);
        }
        if ($dto->sesSecretAccessKey !== null && $dto->sesSecretAccessKey !== '') {
            $this->settingsService->setEncrypted('mail_ses_secret_access_key', $dto->sesSecretAccessKey);
        }

        if ($dto->resendApiKey !== null && $dto->resendApiKey !== '') {
            $this->settingsService->setEncrypted('mail_resend_api_key', $dto->resendApiKey);
        }

        if ($dto->quotaDailyLimit !== null) {
            $this->settingsService->set('mail_quota_daily_limit', (string) $dto->quotaDailyLimit);
        }
        if ($dto->quotaMonthlyLimit !== null) {
            $this->settingsService->set('mail_quota_monthly_limit', (string) $dto->quotaMonthlyLimit);
        }
        if ($dto->quotaWarningThreshold !== null) {
            $this->settingsService->set('mail_quota_warning_threshold', (string) $dto->quotaWarningThreshold);
        }

        event(new MailConfigurationUpdated($dto->driver));
    }

    public function applyToRuntime(): void
    {
        $driver = (string) $this->settingsService->get('mail_driver');

        if ($driver === '') {
            return;
        }

        Config::set('mail.default', $driver);

        $fromAddress = (string) $this->settingsService->get('mail_from_address', '');
        $fromName = (string) $this->settingsService->get('mail_from_name', '');

        if ($fromAddress !== '') {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName);
        }

        if ($driver === 'smtp') {
            $this->applySmtpConfig();
        }

        if ($driver === 'ses') {
            $this->applySesConfig();
        }

        if ($driver === 'resend') {
            $this->applyResendConfig();
        }
    }

    public function isMailEnabled(): bool
    {
        $value = $this->settingsService->get('mail_enabled');

        if ($value === null) {
            return true;
        }

        return $value === '1' || $value === 'true';
    }

    public function getActiveDriver(): MailDriver
    {
        $driver = (string) $this->settingsService->get('mail_driver', 'smtp');

        return MailDriver::from($driver);
    }

    private function applySmtpConfig(): void
    {
        $host = $this->getStringOrNull('mail_smtp_host');
        if ($host !== null) {
            Config::set('mail.mailers.smtp.host', $host);
        }

        $port = $this->getIntOrNull('mail_smtp_port');
        if ($port !== null) {
            Config::set('mail.mailers.smtp.port', $port);
        }

        $username = $this->getStringOrNull('mail_smtp_username');
        if ($username !== null) {
            Config::set('mail.mailers.smtp.username', $username);
        }

        $password = $this->settingsService->getEncrypted('mail_smtp_password');
        if ($password !== null && $password !== '') {
            Config::set('mail.mailers.smtp.password', (string) $password);
        }

        $encryption = $this->getStringOrNull('mail_smtp_encryption');
        if ($encryption !== null) {
            Config::set('mail.mailers.smtp.encryption', $encryption === '' ? null : $encryption);
        }

        $timeout = $this->getIntOrNull('mail_smtp_timeout');
        if ($timeout !== null) {
            Config::set('mail.mailers.smtp.timeout', $timeout);
        }
    }

    private function applyResendConfig(): void
    {
        $apiKey = $this->settingsService->getEncrypted('mail_resend_api_key');
        if ($apiKey !== null && $apiKey !== '') {
            Config::set('services.resend.key', (string) $apiKey);
        }
    }

    private function applySesConfig(): void
    {
        $region = $this->getStringOrNull('mail_ses_region');
        if ($region !== null) {
            Config::set('services.ses.region', $region);
        }

        $accessKeyId = $this->getStringOrNull('mail_ses_access_key_id');
        if ($accessKeyId !== null) {
            Config::set('services.ses.key', $accessKeyId);
        }

        $secretAccessKey = $this->settingsService->getEncrypted('mail_ses_secret_access_key');
        if ($secretAccessKey !== null && $secretAccessKey !== '') {
            Config::set('services.ses.secret', (string) $secretAccessKey);
        }
    }

    private function getStringOrNull(string $key): ?string
    {
        $value = $this->settingsService->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function getIntOrNull(string $key): ?int
    {
        $value = $this->settingsService->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
