<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\TwoFactor\DTOs\TwoFactorSetupDTO;
use App\Application\TwoFactor\DTOs\TwoFactorStatusDTO;
use App\Application\TwoFactor\Services\TwoFactorAppChangeInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Domain\TwoFactor\Events\RecoveryCodesRegenerated;
use App\Domain\TwoFactor\Events\RecoveryCodeUsed;
use App\Domain\TwoFactor\Events\TwoFactorAppChanged;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\Exceptions\TwoFactorUnavailableException;
use App\Domain\TwoFactor\TotpWindowRule;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Domain\TwoFactor\ValueObjects\RecoveryCode;
use App\Domain\TwoFactor\ValueObjects\TotpCode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Infrastructure\QrCodes\TotpQrCodeRendererInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * TOTP on pragmarx/google2fa, which only computes codes: the window, the replay rule, storage and
 * recovery codes are decided here. Every read that leads to a write takes the row lock first, so
 * two requests racing with the same code (or recovery code) never both pass.
 */
final readonly class TwoFactorService implements TwoFactorAppChangeInterface, TwoFactorServiceInterface
{
    public const int RECOVERY_CODE_COUNT = 8;

    /** 32 Base32 characters: the 160 bits RFC 4226 recommends */
    public const int SECRET_LENGTH = 32;

    public function __construct(
        private Google2FA $google2fa,
        private TotpWindowRule $window,
        private TotpQrCodeRendererInterface $qrCodes,
        private SiteBrandingServiceInterface $branding,
        private ImpersonationContextInterface $impersonation,
        private TwoFactorSessionSealInterface $seal,
    ) {}

    public function status(string $userId): TwoFactorStatusDTO
    {
        $id = $this->normalise($userId);
        $row = $id === null ? null : UserTwoFactorModel::query()->find($id);

        if (! $row instanceof UserTwoFactorModel) {
            return TwoFactorStatusDTO::off();
        }

        if ($row->confirmed_at === null) {
            return TwoFactorStatusDTO::off(setupPending: true);
        }

        return new TwoFactorStatusDTO(
            enabled: true,
            confirmedAt: DateTimeImmutable::createFromInterface($row->confirmed_at),
            recoveryCodesRemaining: count($this->hashes($row)),
            setupPending: false,
        );
    }

    public function isEnabled(string $userId): bool
    {
        $id = $this->normalise($userId);

        return $id !== null && UserTwoFactorModel::query()->whereKey($id)->whereNotNull('confirmed_at')->exists();
    }

    public function enabledAmong(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map($this->normalise(...), $userIds))));

        if ($ids === []) {
            return [];
        }

        /** @var list<string> */
        return UserTwoFactorModel::query()
            ->whereIn('user_id', $ids)
            ->whereNotNull('confirmed_at')
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function beginSetup(string $userId): TwoFactorSetupDTO
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::UnknownAccount);
        $secret = $this->totp(fn (): string => $this->google2fa->generateSecretKey(self::SECRET_LENGTH));

        $user = DB::transaction(static function () use ($id, $secret): UserModel {
            // The account's row is locked first: with no two-factor row yet there is nothing else to
            // lock, and two setups racing would both try to insert one
            $user = UserModel::query()->lockForUpdate()->find($id);
            if (! $user instanceof UserModel) {
                throw TwoFactorException::because(TwoFactorFailure::UnknownAccount);
            }

            $row = UserTwoFactorModel::query()->lockForUpdate()->find($id);

            if ($row instanceof UserTwoFactorModel && $row->confirmed_at !== null) {
                throw TwoFactorException::because(TwoFactorFailure::AlreadyEnabled);
            }

            $row ??= new UserTwoFactorModel(['user_id' => $id]);
            $row->fill([
                'secret' => $secret,
                'pending_secret' => null,
                'confirmed_at' => null,
                'recovery_codes' => null,
                'last_used_timestep' => null,
            ])->save();

            return $user;
        });

        $uri = $this->otpauthUri((string) $user->email, $secret);

        return new TwoFactorSetupDTO(
            secret: $secret,
            otpauthUri: $uri,
            qrCodeSvg: $this->totp(fn (): string => $this->qrCodes->renderTotpUri($uri, new QrCodeOptionsDTO, QrCodeFormat::Svg)->contents),
        );
    }

    public function confirmSetup(string $userId, #[\SensitiveParameter] string $code, ?Session $currentSession = null): array
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::NoPendingSetup);
        $totp = TotpCode::tryFrom($code);

        return DB::transaction(function () use ($id, $totp, $currentSession): array {
            $this->lockAccount($id);
            $row = UserTwoFactorModel::query()->lockForUpdate()->find($id);

            if (! $row instanceof UserTwoFactorModel) {
                throw TwoFactorException::because(TwoFactorFailure::NoPendingSetup);
            }

            if ($row->confirmed_at !== null) {
                throw TwoFactorException::because(TwoFactorFailure::AlreadyEnabled);
            }

            $step = $totp instanceof TotpCode ? $this->matchingStep($row, $totp) : null;
            if ($step === null) {
                throw TwoFactorException::because(TwoFactorFailure::InvalidCode);
            }

            $codes = $this->newRecoveryCodes();
            $row->fill([
                'confirmed_at' => now(),
                'recovery_codes' => $this->hashesOf($codes),
                // The code just typed cannot be typed again to sign in
                'last_used_timestep' => $step,
            ])->save();

            // Every session opened before is signed out; the one that turned it on stays
            $this->seal->renew($id, $currentSession);
            $this->announceAfterCommit(new TwoFactorEnabled($id));

            return array_map(static fn (RecoveryCode $recoveryCode): string => $recoveryCode->value, $codes);
        });
    }

    public function verify(string $userId, #[\SensitiveParameter] string $code): bool
    {
        $id = $this->normalise($userId);
        $totp = TotpCode::tryFrom($code);

        if ($id === null || ! $totp instanceof TotpCode) {
            return false;
        }

        return DB::transaction(function () use ($id, $totp): bool {
            $row = UserTwoFactorModel::query()->lockForUpdate()->find($id);

            if (! $row instanceof UserTwoFactorModel || $row->confirmed_at === null) {
                return false;
            }

            $step = $this->matchingStep($row, $totp);
            if ($step === null) {
                return false;
            }

            $row->last_used_timestep = $step;
            $row->save();

            return true;
        });
    }

    public function useRecoveryCode(string $userId, #[\SensitiveParameter] string $code): bool
    {
        $id = $this->normalise($userId);
        $recoveryCode = RecoveryCode::tryFrom($code);

        if ($id === null || ! $recoveryCode instanceof RecoveryCode) {
            return false;
        }

        return DB::transaction(function () use ($id, $recoveryCode): bool {
            $row = UserTwoFactorModel::query()->lockForUpdate()->find($id);

            if (! $row instanceof UserTwoFactorModel || $row->confirmed_at === null) {
                return false;
            }

            $hash = $recoveryCode->hash();
            $found = false;
            $left = [];
            foreach ($this->hashes($row) as $stored) {
                if (! $found && hash_equals($stored, $hash)) {
                    $found = true;

                    continue;
                }

                $left[] = $stored;
            }

            if (! $found) {
                return false;
            }

            $row->recovery_codes = $left;
            $row->save();

            $remaining = count($left);
            $this->announceAfterCommit(new RecoveryCodeUsed($id, $remaining));

            return true;
        });
    }

    public function regenerateRecoveryCodes(string $userId): array
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::NotEnabled);

        return DB::transaction(function () use ($id): array {
            $row = $this->enabledRowOrFail($id);

            $codes = $this->newRecoveryCodes();
            $row->recovery_codes = $this->hashesOf($codes);
            $row->save();

            $this->announceAfterCommit(new RecoveryCodesRegenerated($id));

            return array_map(static fn (RecoveryCode $recoveryCode): string => $recoveryCode->value, $codes);
        });
    }

    public function disable(string $userId, ?Session $currentSession = null): void
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::NotEnabled);

        DB::transaction(function () use ($id, $currentSession): void {
            $this->lockAccount($id);
            $this->enabledRowOrFail($id)->delete();

            $this->seal->renew($id, $currentSession);
            $this->announceAfterCommit(new TwoFactorDisabled($id));
        });
    }

    public function beginAppChange(string $userId): TwoFactorSetupDTO
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::NotEnabled);
        $secret = $this->totp(fn (): string => $this->google2fa->generateSecretKey(self::SECRET_LENGTH));

        $user = DB::transaction(function () use ($id, $secret): UserModel {
            $user = UserModel::query()->lockForUpdate()->find($id);
            if (! $user instanceof UserModel) {
                throw TwoFactorException::because(TwoFactorFailure::NotEnabled);
            }

            // Only the secret waiting changes: the one in use keeps signing the person in
            $row = $this->enabledRowOrFail($id);
            $row->pending_secret = $secret;
            $row->save();

            return $user;
        });

        $uri = $this->otpauthUri((string) $user->email, $secret);

        return new TwoFactorSetupDTO(
            secret: $secret,
            otpauthUri: $uri,
            qrCodeSvg: $this->totp(fn (): string => $this->qrCodes->renderTotpUri($uri, new QrCodeOptionsDTO, QrCodeFormat::Svg)->contents),
        );
    }

    public function confirmAppChange(string $userId, #[\SensitiveParameter] string $code, ?Session $currentSession = null): void
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::NotEnabled);
        $totp = TotpCode::tryFrom($code);

        DB::transaction(function () use ($id, $totp, $currentSession): void {
            $this->lockAccount($id);
            $row = $this->enabledRowOrFail($id);

            $pending = $row->pending_secret;
            if ($pending === null) {
                throw TwoFactorException::because(TwoFactorFailure::NoPendingSetup);
            }

            // The code must belong to the new app: one of the app in use proves nothing about it
            $step = $totp instanceof TotpCode ? $this->matchingStep($row, $totp, $pending, enforceLastUsed: false) : null;
            if ($step === null) {
                throw TwoFactorException::because(TwoFactorFailure::InvalidCode);
            }

            $row->fill([
                'secret' => $pending,
                'pending_secret' => null,
                // The code just typed cannot be typed again to sign in. The new secret has no earlier
                // use to protect, so the code of the app in use that was typed a moment ago (same
                // step) does not make the new app's code wait for the next one
                'last_used_timestep' => max($row->last_used_timestep ?? 0, $step),
            ])->save();

            // Whoever held the old app may hold a session: every other one is signed out
            $this->seal->renew($id, $currentSession);
            $this->announceAfterCommit(new TwoFactorAppChanged($id));
        });
    }

    /**
     * Changes that also write the account's row (the session seal) lock it first, before the
     * two-factor row, in the same order as beginSetup(): two requests taking them the other way
     * round would deadlock. Must run inside a transaction.
     */
    private function lockAccount(string $id): void
    {
        UserModel::query()->withTrashed()->whereKey($id)->lockForUpdate()->first(['id']);
    }

    /**
     * Must run inside a transaction: the row stays locked until it ends.
     */
    private function enabledRowOrFail(string $id): UserTwoFactorModel
    {
        $row = UserTwoFactorModel::query()->lockForUpdate()->find($id);

        if (! $row instanceof UserTwoFactorModel || $row->confirmed_at === null) {
            throw TwoFactorException::because(TwoFactorFailure::NotEnabled);
        }

        return $row;
    }

    /**
     * The step the code belongs to, among those the window still accepts. Every candidate is
     * computed, so the time taken does not tell which one matched.
     */
    private function matchingStep(UserTwoFactorModel $row, TotpCode $code, ?string $secret = null, bool $enforceLastUsed = true): ?int
    {
        $secret ??= $row->secret;
        $lastUsed = $enforceLastUsed ? $row->last_used_timestep : null;
        $match = null;

        foreach ($this->window->candidateSteps(now()->getTimestamp(), $lastUsed) as $step) {
            $expected = $this->totp(fn (): string => $this->google2fa->oathTotp($secret, $step));

            if (hash_equals($expected, $code->value) && $match === null) {
                $match = $step;
            }
        }

        return $match;
    }

    /**
     * @return list<RecoveryCode>
     */
    private function newRecoveryCodes(): array
    {
        $codes = [];

        while (count($codes) < self::RECOVERY_CODE_COUNT) {
            $code = RecoveryCode::generate();
            $codes[$code->value] = $code;
        }

        return array_values($codes);
    }

    /**
     * @param  list<RecoveryCode>  $codes
     * @return list<string>
     */
    private function hashesOf(array $codes): array
    {
        return array_map(static fn (RecoveryCode $code): string => $code->hash(), $codes);
    }

    /**
     * @return list<string>
     */
    private function hashes(UserTwoFactorModel $row): array
    {
        $stored = $row->recovery_codes;

        return is_array($stored) ? array_values(array_filter($stored, is_string(...))) : [];
    }

    /**
     * The issuer is the site's name and the account the email, both in the label and the issuer
     * parameter, as authenticator apps expect (Key URI format).
     */
    private function otpauthUri(string $email, #[\SensitiveParameter] string $secret): string
    {
        $issuer = $this->branding->siteName();

        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($email).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => TotpCode::DIGITS,
            'period' => TotpWindowRule::PERIOD_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The events send the security mails. A failure there must not cost the person what the
     * change returns (recovery codes are shown only once), so it is logged instead.
     */
    private function announceAfterCommit(object $event): void
    {
        DB::afterCommit(static function () use ($event): void {
            try {
                Event::dispatch($event);
            } catch (Throwable $exception) {
                Log::error('Two-factor change committed, but announcing it failed.', [
                    'event' => $event::class,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    /**
     * Runs a call that handles the secret or the URI. Whatever it throws is replaced by an
     * exception with no message from the cause and no previous one, whose trace could carry them.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws TwoFactorUnavailableException
     */
    private function totp(callable $call): mixed
    {
        try {
            return $call();
        } catch (Throwable) {
            throw new TwoFactorUnavailableException;
        }
    }

    private function refuseWhileImpersonating(): void
    {
        if ($this->impersonation->isActive()) {
            throw TwoFactorException::because(TwoFactorFailure::Impersonating);
        }
    }

    /**
     * Account ids are UUIDs, compared in lower case; anything else names no account (and is never
     * sent to a uuid column, which PostgreSQL would refuse).
     */
    private function normalise(string $userId): ?string
    {
        $id = strtolower(trim($userId));

        return Str::isUuid($id) ? $id : null;
    }
}
