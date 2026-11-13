<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Solo mirar» (impersonation, read_only): the site only reads. Every request that is not GET or
 * HEAD is refused before its controller, so nothing looks saved when it is not. In the web group,
 * right after ApplyImpersonation (which tells the context whether an impersonation is active);
 * in simulate mode, or without an impersonation, it does nothing.
 *
 * Page visits go back with the notice; JSON gets a 403 whose `error` and header the frontend
 * recognises (useImpersonationReadOnly()) without reading the translated text.
 *
 * Left alone on purpose:
 * - `impersonation.stop` and the logout routes (ApplyImpersonation::LOGOUT_ROUTES): the way out;
 * - `realtime.auth` (`POST /broadcasting/auth`): subscribing to a channel only reads;
 * - Livewire update requests (by route name, never by a header the client can send) and the panel, which #576 covers with their own check.
 * What a request slips past this (a GET that writes) is the safety net's business (#572).
 */
final readonly class BlockSiteWritesWhenImpersonatingReadOnly
{
    /** `error` field of the JSON 403, stable across locales; the frontend recognises it by it */
    public const string JSON_ERROR_CODE = 'impersonation_read_only';

    /** Header set to `1` on the JSON 403, readable from a fetch() Response without the body */
    public const string JSON_ERROR_HEADER = 'X-Impersonation-Read-Only';

    /** @var list<string> */
    private const array EXEMPT_ROUTES = [
        'impersonation.stop',
        ...ApplyImpersonation::LOGOUT_ROUTES,
        'realtime.auth',
        '*livewire.update',
        'filament.*',
    ];

    public function __construct(
        private ImpersonationContextInterface $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->mode() !== ImpersonationMode::ReadOnly
            || $request->isMethodSafe()
            || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        $message = __('impersonation.read_only_notice', ['name' => $this->targetName()]);

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json(
                ['message' => $message, 'error' => self::JSON_ERROR_CODE],
                Response::HTTP_FORBIDDEN,
                [self::JSON_ERROR_HEADER => '1'],
            );
        }

        return back()->with('error', $message);
    }

    private function targetName(): string
    {
        $target = $this->context->target();

        if ($target instanceof UserModel) {
            return $target->display_name ?? $target->name;
        }

        return __('impersonation.unknown_account');
    }
}
