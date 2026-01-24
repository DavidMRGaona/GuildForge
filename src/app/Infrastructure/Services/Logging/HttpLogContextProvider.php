<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Logging;

use App\Application\Services\LogContextProviderInterface;
use Illuminate\Http\Request;

/**
 * Provides HTTP request context for logging.
 */
final class HttpLogContextProvider implements LogContextProviderInterface
{
    public function __construct(
        private readonly ?Request $request = null,
    ) {}

    public function getRequestId(): ?string
    {
        return $this->request?->header('X-Request-ID');
    }

    public function getClientIp(): ?string
    {
        return $this->request?->ip();
    }

    public function getUserAgent(): ?string
    {
        return $this->request?->userAgent();
    }

    public function getRequestUrl(): ?string
    {
        return $this->request?->fullUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalContext(): array
    {
        if ($this->request === null) {
            return [];
        }

        return [
            'method' => $this->request->method(),
            'route' => $this->request->route()?->getName(),
        ];
    }
}
