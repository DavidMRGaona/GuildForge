<?php

declare(strict_types=1);

namespace App\Application\Services;

/**
 * Provides contextual information for logging.
 * This interface decouples logging from specific contexts (HTTP, CLI, etc.).
 */
interface LogContextProviderInterface
{
    /**
     * Get the request ID for log correlation.
     */
    public function getRequestId(): ?string;

    /**
     * Get the client IP address.
     */
    public function getClientIp(): ?string;

    /**
     * Get the user agent string.
     */
    public function getUserAgent(): ?string;

    /**
     * Get the request URL.
     */
    public function getRequestUrl(): ?string;

    /**
     * Get additional context as an array.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalContext(): array;
}
