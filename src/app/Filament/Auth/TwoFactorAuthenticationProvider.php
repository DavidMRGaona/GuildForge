<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Application\TwoFactor\DTOs\TwoFactorChallengeCheckDTO;
use App\Application\TwoFactor\Services\TwoFactorChallengeInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\TwoFactor\TwoFactorFailure;
use Closure;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The panel's second factor (core#770): Filament's own challenge step on the login page, backed by
 * the core service, so the panel and the site check codes the same way. Managing it lives in the
 * account settings of the site, never on a Filament profile page.
 *
 * The answer to each code is kept until PanelLogin forgets it at the end of the request, so a
 * form validated twice never refuses an accepted code as a repeated one.
 */
final class TwoFactorAuthenticationProvider implements MultiFactorAuthenticationProvider
{
    public const string ID = 'core_two_factor';

    /** @var array<string, TwoFactorChallengeCheckDTO> */
    private array $checked = [];

    private ?TwoFactorChallengeCheckDTO $lastCheck = null;

    public function isEnabled(Authenticatable $user): bool
    {
        return app(TwoFactorStatusReaderInterface::class)->isEnabled((string) $user->getAuthIdentifier());
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getLoginFormLabel(): string
    {
        return (string) __('two_factor.panel.label');
    }

    /**
     * @return array<never>
     */
    public function getManagementSchemaComponents(): array
    {
        return [];
    }

    /**
     * @return array<int, Checkbox|TextInput>
     */
    public function getChallengeFormComponents(Authenticatable $user): array
    {
        $userId = (string) $user->getAuthIdentifier();

        return [
            TextInput::make('code')
                ->label(static fn (Get $get): string => (string) __($get('recovery') ? 'two_factor.panel.recovery_code' : 'two_factor.panel.app_code'))
                ->required()
                ->maxLength(64)
                ->autocomplete(static fn (Get $get): string => $get('recovery') ? 'off' : 'one-time-code')
                ->extraInputAttributes(static fn (Get $get): array => ['inputmode' => $get('recovery') ? 'text' : 'numeric'])
                ->autofocus()
                ->rule(fn (Get $get): Closure => $this->codeRule($userId, static fn (): bool => (bool) $get('recovery'))),
            Checkbox::make('recovery')
                ->label(__('two_factor.panel.use_recovery_code'))
                ->live(),
        ];
    }

    /**
     * The validation rule of the code field.
     *
     * @param  Closure(): bool  $usesRecoveryCode
     * @return Closure(string, mixed, Closure): void
     */
    public function codeRule(string $userId, Closure $usesRecoveryCode): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($userId, $usesRecoveryCode): void {
            $result = $this->check($userId, is_string($value) ? $value : '', $usesRecoveryCode());

            if (! $result->passed) {
                $fail(match ($result->failure) {
                    TwoFactorFailure::TooManyAttempts => __('auth.two_factor.too_many_attempts', ['seconds' => $result->retryAfter]),
                    TwoFactorFailure::Unavailable => __('auth.two_factor.unavailable'),
                    default => __('auth.two_factor.invalid_code'),
                });
            }
        };
    }

    /**
     * Checks a code once: the same code again for the same account answers the first check.
     */
    public function check(string $userId, #[\SensitiveParameter] string $code, bool $recoveryCode): TwoFactorChallengeCheckDTO
    {
        $key = hash('sha256', $userId."\0".($recoveryCode ? 'recovery' : 'app')."\0".$code);

        return $this->lastCheck = $this->checked[$key] ??= app(TwoFactorChallengeInterface::class)->check($userId, $code, $recoveryCode);
    }

    /**
     * What the last code checked in this request was answered, if any.
     */
    public function lastCheck(): ?TwoFactorChallengeCheckDTO
    {
        return $this->lastCheck;
    }

    /**
     * Called by PanelLogin at the end of every request: the panel keeps one instance per process.
     */
    public function forgetChecks(): void
    {
        $this->checked = [];
        $this->lastCheck = null;
    }
}
