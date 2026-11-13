<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

use App\Application\Impersonation\Services\ImpersonationLoggerInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;

final readonly class ImpersonationLogger implements ImpersonationLoggerInterface
{
    /** Longer user agents are cut: they only help to recognise the browser */
    public const int USER_AGENT_LENGTH = 512;

    public function started(string $impersonatorId, string $targetId, ImpersonationMode $mode, ?string $ip, ?string $userAgent): string
    {
        $entry = ImpersonationLogEntryModel::query()->create([
            'impersonator_id' => $impersonatorId,
            'target_id' => $targetId,
            'mode' => $mode,
            'started_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, self::USER_AGENT_LENGTH),
        ]);

        return $entry->id;
    }

    public function ended(string $logId, ImpersonationEndReason $reason): void
    {
        ImpersonationLogEntryModel::query()
            ->whereKey($logId)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
                'ended_reason' => $reason->value,
            ]);
    }
}
