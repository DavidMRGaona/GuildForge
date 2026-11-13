<?php

declare(strict_types=1);

namespace App\Http\Middleware\Concerns;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Laravel's AuthenticateSession, with the session's owner in place of the guard's user.
 *
 * While an administrator impersonates, the guard holds the other account, but the session
 * (and its stored password hash) is the administrator's. Checking or storing the other
 * account's hash would sign the administrator out on the next request. So during an
 * impersonation both checks and the hash stored after the request use the impersonator.
 *
 * Mirrors the parent's handle() and storePasswordHashInSession(); the rest is inherited.
 *
 * @property ImpersonationContextInterface $impersonation
 */
trait ChecksTheImpersonatorsSession
{
    /**
     * @param  Request  $request
     */
    public function handle($request, Closure $next): mixed
    {
        $owner = $this->sessionOwner($request);

        if (! $request->hasSession() || $owner === null || ! $owner->getAuthPassword()) {
            return $next($request);
        }

        if ($this->guard()->viaRemember()) {
            $passwordHashFromCookie = explode('|', (string) $request->cookies->get($this->guard()->getRecallerName()))[2] ?? null;

            if (! $passwordHashFromCookie
                || ! $this->validatePasswordHash($owner->getAuthPassword(), $passwordHashFromCookie)) {
                $this->logout($request);
            }
        }

        if (! $request->session()->has($this->passwordHashKey())) {
            $this->storePasswordHashInSession($request);
        }

        $sessionPasswordHash = $request->session()->get($this->passwordHashKey());

        if (! is_string($sessionPasswordHash) || ! $this->validatePasswordHash($owner->getAuthPassword(), $sessionPasswordHash)) {
            $this->logout($request);
        }

        return tap($next($request), function () use ($request): void {
            if (! is_null($this->guard()->user())) {
                $this->storePasswordHashInSession($request);
            }
        });
    }

    /**
     * @param  Request  $request
     */
    protected function storePasswordHashInSession($request): void
    {
        $owner = $this->sessionOwner($request);

        if ($owner === null) {
            return;
        }

        $passwordHash = $owner->getAuthPassword();

        try {
            $passwordHash = $this->guard()->hashPasswordForCookie($passwordHash);
        } catch (BadMethodCallException) {
        }

        $request->session()->put([$this->passwordHashKey() => $passwordHash]);
    }

    /**
     * Asked each time, not once: the impersonation may start applying or end during the request.
     */
    private function sessionOwner(Request $request): ?Authenticatable
    {
        $user = $this->impersonation->impersonator() ?? $request->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    private function passwordHashKey(): string
    {
        return 'password_hash_'.$this->auth->getDefaultDriver();
    }
}
