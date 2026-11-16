<?php

declare(strict_types=1);

namespace App\Application\TwoFactor\Services;

use App\Application\TwoFactor\DTOs\PendingTwoFactorLoginDTO;
use Illuminate\Contracts\Session\Session;

/**
 * The site's sign-in waiting for its second factor (core#770), kept in the session. The limits
 * are TwoFactorChallengeRule's: five minutes, then it is gone; ten wrong codes, then it is dropped.
 */
interface PendingTwoFactorLoginInterface
{
    /**
     * Starts one in place of any other, and gives the session a new id.
     */
    public function begin(Session $session, string $userId, bool $remember): PendingTwoFactorLoginDTO;

    /**
     * The one in course, or null when there is none, it expired or it cannot be read (dropped then).
     */
    public function current(Session $session): ?PendingTwoFactorLoginDTO;

    /**
     * Counts a wrong code. Returns false when that was the last one allowed: it is dropped.
     */
    public function recordFailure(Session $session): bool;

    public function forget(Session $session): void;
}
