<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Domain\TwoFactor\Events\TwoFactorReset;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resets an account's two-factor authentication (core#771). Locks the account's row first and the
 * two-factor row second, the order TwoFactorService uses, so a reset racing a setup or a code never
 * deadlocks; the seal is renewed in the same transaction, so the change and the sign-out commit
 * (or roll back) together.
 */
final readonly class TwoFactorResetService implements TwoFactorResetInterface
{
    public function __construct(
        private TwoFactorSessionSealInterface $seal,
        private ImpersonationContextInterface $impersonation,
    ) {}

    public function reset(string $userId, ?string $actorId): bool
    {
        if ($this->impersonation->isActive()) {
            throw TwoFactorException::because(TwoFactorFailure::Impersonating);
        }

        $id = strtolower(trim($userId));
        if (! Str::isUuid($id)) {
            throw TwoFactorException::because(TwoFactorFailure::UnknownAccount);
        }

        return DB::transaction(function () use ($id, $actorId): bool {
            if (! UserModel::query()->withTrashed()->whereKey($id)->lockForUpdate()->exists()) {
                throw TwoFactorException::because(TwoFactorFailure::UnknownAccount);
            }

            $row = UserTwoFactorModel::query()->lockForUpdate()->find($id);

            if (! $row instanceof UserTwoFactorModel) {
                return false;
            }

            $wasEnabled = $row->confirmed_at !== null;
            $row->delete();

            // A setup nobody confirmed never signed anything out and has nothing to announce
            if (! $wasEnabled) {
                return false;
            }

            // No session is kept: the person is not the one signed in here
            $this->seal->renew($id);
            $this->announceAfterCommit(new TwoFactorReset($id, $actorId));

            return true;
        });
    }

    /**
     * The event sends the security mail. A failure there must not undo the reset (it is already
     * committed), so it is logged instead.
     */
    private function announceAfterCommit(TwoFactorReset $event): void
    {
        DB::afterCommit(static function () use ($event): void {
            try {
                Event::dispatch($event);
            } catch (Throwable $exception) {
                Log::error('Two-factor reset committed, but announcing it failed.', [
                    'event' => $event::class,
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
