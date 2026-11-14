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

    /**
     * @param  list<string>|null  $roleIds  UUIDs of the roles being viewed; null when the
     *                                      impersonation is of an account
     */
    public function __construct(
        public string $targetId,
        public string $logId,
        public ImpersonationMode $mode,
        public string $impersonatorId,
        public ?array $roleIds = null,
    ) {}

    public function isRoleView(): bool
    {
        return $this->roleIds !== null;
    }

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

        // A state stored before role views has no role_ids and reads as an account; one that
        // has the key but not a usable list is dropped, never read as an account
        $roleIds = null;
        if (array_key_exists('role_ids', $value)) {
            $roleIds = $value['role_ids'];

            if (! is_array($roleIds) || $roleIds === [] || ! array_is_list($roleIds)
                || array_filter($roleIds, static fn (mixed $id): bool => ! is_string($id) || ! Str::isUuid($id)) !== []) {
                return null;
            }
        }

        return new self($targetId, $logId, $mode, $impersonatorId, $roleIds);
    }

    /**
     * @return array{target_id: string, log_id: string, mode: string, impersonator_id: string, role_ids?: list<string>}
     */
    public function toArray(): array
    {
        $state = [
            'target_id' => $this->targetId,
            'log_id' => $this->logId,
            'mode' => $this->mode->value,
            'impersonator_id' => $this->impersonatorId,
        ];

        if ($this->roleIds !== null) {
            $state['role_ids'] = $this->roleIds;
        }

        return $state;
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
