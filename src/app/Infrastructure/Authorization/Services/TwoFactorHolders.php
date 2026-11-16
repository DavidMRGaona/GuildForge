<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Infrastructure\Impersonation\RoleViewAccountIds;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Who has two-factor authentication on, as roles that require it (core#772) ask: only through
 * TwoFactorStatusReaderInterface (never the table), plus the throwaway accounts of role views.
 * A reader that fails counts as off, so a role that requires it stays paused (fails closed).
 * Bound scoped, so it can count its failures for the request: an answer computed while the
 * reader failed is never cached (AuthorizationService::cachedLookup()).
 *
 * Both are resolved when asked: the reader is built from services that may use authorization.
 */
final class TwoFactorHolders
{
    private int $failures = 0;

    /**
     * How many times the reader failed in this request or job.
     */
    public function failures(): int
    {
        return $this->failures;
    }

    public function has(string $userId): bool
    {
        if (app(RoleViewAccountIds::class)->has($userId)) {
            return true;
        }

        try {
            return app(TwoFactorStatusReaderInterface::class)->isEnabled($userId);
        } catch (Throwable $exception) {
            $this->failures++;
            Log::warning('Two-factor status unreadable, roles that require it stay paused.', [
                'user_id' => $userId,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * The ids among those given that have it on, in lower case. A reader that fails gives none.
     *
     * @param  list<string>  $userIds
     * @return array<string, true>
     */
    public function among(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $roleViews = app(RoleViewAccountIds::class);
        $on = [];

        foreach ($userIds as $userId) {
            if ($roleViews->has($userId)) {
                $on[strtolower($userId)] = true;
            }
        }

        try {
            foreach (app(TwoFactorStatusReaderInterface::class)->enabledAmong($userIds) as $userId) {
                $on[strtolower($userId)] = true;
            }
        } catch (Throwable $exception) {
            $this->failures++;
            Log::warning('Two-factor status unreadable, roles that require it stay paused.', [
                'exception' => $exception::class,
            ]);
        }

        return $on;
    }
}
