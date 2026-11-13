<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Filament\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Volver a mi cuenta»: ends the impersonation and goes back to the account's page in the
 * panel. A site route, so it works from the site and from the panel, whoever the target is.
 */
final class StopImpersonationController extends Controller
{
    public function __invoke(
        ImpersonationContextInterface $context,
        ImpersonationServiceInterface $impersonation,
        Request $request,
    ): Response {
        $targetId = $context->target()?->getAuthIdentifier();

        $impersonation->stop(ImpersonationEndReason::Stopped);

        $url = $targetId === null
            ? route('home')
            : UserResource::getUrl('edit', ['record' => $targetId]);

        // The site's band sends an Inertia visit: the panel is not an Inertia page, so the
        // browser has to leave with a full visit instead of following the redirect by XHR
        if ($request->header('X-Inertia') !== null) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }
}
