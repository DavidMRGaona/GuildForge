<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use App\Domain\Impersonation\Enums\ImpersonationMode;
use Illuminate\Support\Str;

/**
 * What the administrator's session holds while impersonating, under SESSION_KEY. The
 * impersonator's id ties the state to the account the session belongs to: a request signed
 * in as anybody else revokes it.
 */
final readonly class ImpersonationSessionState
{
    public const string SESSION_KEY = 'impersonation';

    public function __construct(
        public string $targetId,
        public string $logId,
        public ImpersonationMode $mode,
        public string $impersonatorId,
    ) {}

    /**
     * null for anything but a complete state (ids are UUIDs): the caller drops it.
     */
    public static function fromSession(mixed $value): ?self
    {
        if (! is_array($value)) {
            return null;
        }

        $targetId = $value['target_id'] ?? null;
        $logId = $value['log_id'] ?? null;
        $mode = is_string($value['mode'] ?? null) ? ImpersonationMode::tryFrom($value['mode']) : null;
        $impersonatorId = $value['impersonator_id'] ?? null;

        if (! is_string($targetId) || ! Str::isUuid($targetId)
            || ! is_string($logId) || ! Str::isUuid($logId)
            || ! $mode instanceof ImpersonationMode
            || ! is_string($impersonatorId) || ! Str::isUuid($impersonatorId)) {
            return null;
        }

        return new self($targetId, $logId, $mode, $impersonatorId);
    }

    /**
     * @return array{target_id: string, log_id: string, mode: string, impersonator_id: string}
     */
    public function toArray(): array
    {
        return [
            'target_id' => $this->targetId,
            'log_id' => $this->logId,
            'mode' => $this->mode->value,
            'impersonator_id' => $this->impersonatorId,
        ];
    }

    /**
     * The log entry of whatever the session holds, even an incomplete state, so that it can
     * still be closed.
     */
    public static function logIdIn(mixed $value): ?string
    {
        $logId = is_array($value) ? ($value['log_id'] ?? null) : null;

        return is_string($logId) && Str::isUuid($logId) ? $logId : null;
    }
}
