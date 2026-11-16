<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\TwoFactor\DTOs\TwoFactorSetupDTO;
use App\Application\TwoFactor\DTOs\TwoFactorStatusDTO;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\TwoFactor\Events\RecoveryCodesRegenerated;
use App\Domain\TwoFactor\Events\RecoveryCodeUsed;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TotpWindowRule;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Domain\TwoFactor\ValueObjects\RecoveryCode;
use App\Domain\TwoFactor\ValueObjects\TotpCode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Infrastructure\QrCodes\TotpQrCodeRendererInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP on pragmarx/google2fa, which only computes codes: the window, the replay rule, storage and
 * recovery codes are decided here. Every read that leads to a write takes the row lock first, so
 * two requests racing with the same code (or recovery code) never both pass.
 */
final readonly class TwoFactorService implements TwoFactorServiceInterface
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
        $secret = $this->google2fa->generateSecretKey(self::SECRET_LENGTH);

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
            qrCodeSvg: $this->qrCodes->renderTotpUri($uri, new QrCodeOptionsDTO, QrCodeFormat::Svg)->contents,
        );
    }

    public function confirmSetup(string $userId, #[\SensitiveParameter] string $code, ?string $currentSessionId = null): array
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::NoPendingSetup);
        $totp = TotpCode::tryFrom($code);

        return DB::transaction(function () use ($id, $totp, $currentSessionId): array {
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

            DB::afterCommit(function () use ($id, $currentSessionId): void {
                $this->closeOtherSessions($id, $currentSessionId);
                $this->rotateRememberToken($id);
                Event::dispatch(new TwoFactorEnabled($id));
            });

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
            DB::afterCommit(static fn () => Event::dispatch(new RecoveryCodeUsed($id, $remaining)));

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

            DB::afterCommit(static fn () => Event::dispatch(new RecoveryCodesRegenerated($id)));

            return array_map(static fn (RecoveryCode $recoveryCode): string => $recoveryCode->value, $codes);
        });
    }

    public function disable(string $userId): void
    {
        $this->refuseWhileImpersonating();

        $id = $this->normalise($userId) ?? throw TwoFactorException::because(TwoFactorFailure::NotEnabled);

        DB::transaction(function () use ($id): void {
            $this->enabledRowOrFail($id)->delete();

            DB::afterCommit(static fn () => Event::dispatch(new TwoFactorDisabled($id)));
        });
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
    private function matchingStep(UserTwoFactorModel $row, TotpCode $code): ?int
    {
        $match = null;

        foreach ($this->window->candidateSteps(now()->getTimestamp(), $row->last_used_timestep) as $step) {
            if (hash_equals($this->google2fa->oathTotp($row->secret, $step), $code->value) && $match === null) {
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
     * Sessions in the database are closed by deleting their rows. Other drivers cannot list an
     * account's sessions: the sign-in challenge (core#770) closes any session of an account with
     * two-factor authentication that did not pass it.
     */
    private function closeOtherSessions(string $id, ?string $currentSessionId): void
    {
        $driver = config('session.driver');

        if ($driver !== 'database') {
            Log::warning('Two-factor authentication turned on: other sessions cannot be closed with this session driver', [
                'user_id' => $id,
                'driver' => is_string($driver) ? $driver : null,
            ]);

            return;
        }

        $connection = config('session.connection');
        $table = config('session.table');

        DB::connection(is_string($connection) ? $connection : null)
            ->table(is_string($table) ? $table : 'sessions')
            ->where('user_id', $id)
            ->when($currentSessionId !== null, static fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }

    /**
     * «Remember me» cookies carry the token: a new one invalidates every cookie issued before.
     */
    private function rotateRememberToken(string $id): void
    {
        UserModel::query()->withTrashed()->whereKey($id)->update(['remember_token' => Str::random(60)]);
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
