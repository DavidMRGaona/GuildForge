<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor\SignIn;

use App\Application\TwoFactor\DTOs\PendingTwoFactorLoginDTO;
use App\Application\TwoFactor\Services\PendingTwoFactorLoginInterface;
use App\Domain\TwoFactor\TwoFactorChallengeRule;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final readonly class SessionPendingTwoFactorLogin implements PendingTwoFactorLoginInterface
{
    public const string SESSION_KEY = 'two_factor_login';

    public function begin(Session $session, string $userId, bool $remember): PendingTwoFactorLoginDTO
    {
        $pending = new PendingTwoFactorLoginDTO(Str::random(40), $userId, $remember, $this->now(), 0);

        // A new id before anything else is written, so a session id fixed beforehand gets nothing,
        // and no seal from an earlier sign-in: only the challenge stamps it
        $session->regenerate();
        $session->forget(TwoFactorSessionSeal::SESSION_KEY);
        $this->write($session, $pending);

        return $pending;
    }

    public function current(Session $session): ?PendingTwoFactorLoginDTO
    {
        $pending = $this->read($session->get(self::SESSION_KEY));

        if (! $pending instanceof PendingTwoFactorLoginDTO || TwoFactorChallengeRule::hasExpired($pending->startedAt, $this->now())) {
            $this->forget($session);

            return null;
        }

        return $pending;
    }

    public function recordFailure(Session $session): bool
    {
        $pending = $this->current($session);

        if (! $pending instanceof PendingTwoFactorLoginDTO) {
            return false;
        }

        $failures = $pending->failures + 1;

        if (TwoFactorChallengeRule::isExhausted($failures)) {
            $this->forget($session);

            return false;
        }

        $this->write($session, new PendingTwoFactorLoginDTO(
            $pending->token,
            $pending->userId,
            $pending->remember,
            $pending->startedAt,
            $failures,
        ));

        return true;
    }

    public function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }

    private function write(Session $session, PendingTwoFactorLoginDTO $pending): void
    {
        $session->put(self::SESSION_KEY, [
            'token' => $pending->token,
            'user_id' => $pending->userId,
            'remember' => $pending->remember,
            'started_at' => $pending->startedAt,
            'failures' => $pending->failures,
        ]);
    }

    private function read(mixed $stored): ?PendingTwoFactorLoginDTO
    {
        if (! is_array($stored)
            || ! is_string($stored['token'] ?? null)
            || ! is_string($stored['user_id'] ?? null)
            || ! is_bool($stored['remember'] ?? null)
            || ! is_int($stored['started_at'] ?? null)
            || ! is_int($stored['failures'] ?? null)) {
            return null;
        }

        return new PendingTwoFactorLoginDTO(
            $stored['token'],
            $stored['user_id'],
            $stored['remember'],
            $stored['started_at'],
            $stored['failures'],
        );
    }

    private function now(): int
    {
        return Carbon::now()->getTimestamp();
    }
}
