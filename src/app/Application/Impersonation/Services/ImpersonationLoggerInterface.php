<?php

declare(strict_types=1);

namespace App\Application\Impersonation\Services;

use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;

/**
 * Writes the impersonation log (table impersonation_log): who impersonated whom, in which
 * mode, from where, when it started and when and why it ended. Entries are never deleted.
 *
 * Call it outside any transaction a request rolls back, or the entry is lost with it.
 */
interface ImpersonationLoggerInterface
{
    /**
     * Opens an entry and returns its id. $viewedRoles is set when the administrator views the
     * site as roles instead of as an account.
     *
     * @param  list<ViewedRoleDTO>|null  $viewedRoles
     */
    public function started(string $impersonatorId, string $targetId, ImpersonationMode $mode, ?string $ip, ?string $userAgent, ?array $viewedRoles = null): string;

    /**
     * Closes the entry; one already closed keeps its first end.
     */
    public function ended(string $logId, ImpersonationEndReason $reason): void;
}
