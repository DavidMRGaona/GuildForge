<?php

declare(strict_types=1);

namespace App\Infrastructure\Impersonation;

/**
 * The throwaway accounts RoleViewAccount built in this request or job (bound scoped). A role view
 * shows what the roles give, so these accounts count as having two-factor authentication on
 * (core#772): no row is written for it, and an account outside this list never passes for one.
 */
final class RoleViewAccountIds
{
    /** @var array<string, true> lower-case ids */
    private array $ids = [];

    public function add(string $userId): void
    {
        $this->ids[strtolower($userId)] = true;
    }

    public function has(string $userId): bool
    {
        return isset($this->ids[strtolower($userId)]);
    }
}
