<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\TwoFactor\Services\TwoFactorAppChangeInterface;
use App\Application\TwoFactor\Services\TwoFactorDisableRule;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Domain\TwoFactor\ValueObjects\TotpCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\BeginTwoFactorRequest;
use App\Http\Requests\Profile\ConfirmTwoFactorRequest;
use App\Http\Requests\Profile\TwoFactorChangeRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Turning two-factor authentication on, renewing its recovery codes and turning it off from
 * «Ajustes de la cuenta → Seguridad» (core#769). The secret and the recovery codes are only ever
 * in the answer of the request that creates them (never cached, never in page props or flash).
 * The service owns every rule about codes; this only asks for what a person must prove first: the
 * password and, for an account that already has it, a code.
 */
final class ProfileTwoFactorController extends Controller
{
    /** Answers that carry a secret or recovery codes must never be kept by a cache */
    private const array NO_STORE = ['Cache-Control' => 'no-store, private'];

    public function __construct(
        private readonly TwoFactorServiceInterface $twoFactor,
        private readonly TwoFactorAppChangeInterface $appChange,
        private readonly TwoFactorDisableRule $disableRule,
        private readonly ImpersonationContextInterface $impersonation,
    ) {}

    /**
     * Starts a setup: a new secret, in place of a setup not confirmed yet.
     */
    public function begin(BeginTwoFactorRequest $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $setup = $this->twoFactor->beginSetup($this->userId($request));

            // The otpauth address holds the secret too, so only the key and the picture go out
            return response()->json([
                'secret' => $setup->secret,
                'groupedSecret' => $setup->groupedSecret(),
                'qrCodeSvg' => $setup->qrCodeSvg,
            ], 200, self::NO_STORE);
        });
    }

    /**
     * Turns it on with the first code from the app; the recovery codes are shown only now.
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            // The current session is passed on so that it stays signed in: every other one is closed
            $codes = $this->twoFactor->confirmSetup($this->userId($request), $request->code(), $request->session());

            return response()->json(['recoveryCodes' => $codes], 200, self::NO_STORE);
        });
    }

    /**
     * Starts changing the authenticator app: a new secret kept apart, while the one in use keeps
     * working. Asks for the factor in use as well as the password: with only the password, whoever
     * held a signed-in session could take the second factor over.
     */
    public function beginAppChange(TwoFactorChangeRequest $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $this->proveCode($request);
            $setup = $this->appChange->beginAppChange($this->userId($request));

            return response()->json([
                'secret' => $setup->secret,
                'groupedSecret' => $setup->groupedSecret(),
                'qrCodeSvg' => $setup->qrCodeSvg,
            ], 200, self::NO_STORE);
        });
    }

    /**
     * Swaps the secret for the new one with a code from the new app. The recovery codes stay and
     * every other session is signed out, the current one kept.
     */
    public function confirmAppChange(ConfirmTwoFactorRequest $request): JsonResponse|Response
    {
        return $this->guarded(function () use ($request): Response {
            $this->appChange->confirmAppChange($this->userId($request), $request->code(), $request->session());

            return response()->noContent();
        });
    }

    /**
     * New recovery codes in place of every old one.
     */
    public function recoveryCodes(TwoFactorChangeRequest $request): JsonResponse
    {
        return $this->guarded(function () use ($request): JsonResponse {
            $this->proveCode($request);

            return response()->json(
                ['recoveryCodes' => $this->twoFactor->regenerateRecoveryCodes($this->userId($request))],
                200,
                self::NO_STORE,
            );
        });
    }

    /**
     * Turns it off, unless something (a role that requires it) says the account cannot.
     */
    public function disable(TwoFactorChangeRequest $request): JsonResponse|Response
    {
        return $this->guarded(function () use ($request): JsonResponse|Response {
            $userId = $this->userId($request);

            if (! $this->disableRule->allowed($userId)) {
                return response()->json(['message' => __('two_factor.profile.cannot_disable')], 403, self::NO_STORE);
            }

            $this->proveCode($request);
            $this->twoFactor->disable($userId, $request->session());

            return response()->noContent();
        });
    }

    /**
     * A code from the app or a recovery code, of the signed-in account. A wrong one is an error on
     * the field, never a message that tells which of the two kinds it was.
     *
     * @throws ValidationException
     */
    private function proveCode(TwoFactorChangeRequest $request): void
    {
        $userId = $this->userId($request);
        $code = $request->code();

        // Nothing to prove a code against: a conflict, not a wrong code
        if (! $this->twoFactor->isEnabled($userId)) {
            throw TwoFactorException::because(TwoFactorFailure::NotEnabled);
        }

        $accepted = TotpCode::tryFrom($code) instanceof TotpCode
            ? $this->twoFactor->verify($userId, $code)
            : $this->twoFactor->useRecoveryCode($userId, $code);

        if (! $accepted) {
            throw ValidationException::withMessages(['code' => __('two_factor.profile.invalid_code')]);
        }
    }

    /**
     * Runs a change and turns a failure of the service into an answer the page can show.
     *
     * @template T of JsonResponse|Response
     *
     * @param  callable(): T  $change
     * @return T|JsonResponse
     */
    private function guarded(callable $change): JsonResponse|Response
    {
        // Refused before asking for any code, so an administrator looking at the account uses none up
        if ($this->impersonation->isActive()) {
            return $this->refusal(TwoFactorFailure::Impersonating);
        }

        try {
            return $change();
        } catch (TwoFactorException $exception) {
            if ($exception->reason === TwoFactorFailure::InvalidCode) {
                // Only the first code of a new app is checked here (the factor in use is proved by
                // proveCode(), which also accepts a recovery code), so the text must not offer one
                throw ValidationException::withMessages(['code' => __('two_factor.profile.invalid_new_code')]);
            }

            return $this->refusal($exception->reason);
        }
    }

    private function refusal(TwoFactorFailure $reason): JsonResponse
    {
        $status = match ($reason) {
            TwoFactorFailure::Impersonating => 403,
            TwoFactorFailure::Unavailable => 503,
            default => 409,
        };

        return response()->json(
            ['message' => __('two_factor.profile.refused.'.$reason->value), 'reason' => $reason->value],
            $status,
            self::NO_STORE,
        );
    }

    private function userId(Request $request): string
    {
        /** @var UserModel $user */
        $user = $request->user();

        return (string) $user->id;
    }
}
