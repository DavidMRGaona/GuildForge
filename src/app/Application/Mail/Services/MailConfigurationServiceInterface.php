<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\Response\MailConfigurationResponseDTO;
use App\Application\Mail\DTOs\UpdateMailConfigurationDTO;
use App\Domain\Mail\Enums\MailDriver;

interface MailConfigurationServiceInterface
{
    /**
     * Get the current mail configuration (passwords masked).
     */
    public function getConfiguration(): MailConfigurationResponseDTO;

    /**
     * Update the mail configuration.
     */
    public function updateConfiguration(UpdateMailConfigurationDTO $dto): void;

    /**
     * Apply stored settings to Laravel's runtime config.
     */
    public function applyToRuntime(): void;

    /**
     * Check if mail sending is enabled.
     */
    public function isMailEnabled(): bool;

    /**
     * Get the currently active mail driver.
     */
    public function getActiveDriver(): MailDriver;
}
