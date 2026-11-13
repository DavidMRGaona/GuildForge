<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Filament\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * «Volver a mi cuenta»: ends the impersonation and goes back to the account's page in the
 * panel. A site route, so it works from the site and from the panel, whoever the target is.
 */
final class StopImpersonationController extends Controller
{
    public function __invoke(
        ImpersonationContextInterface $context,
        ImpersonationServiceInterface $impersonation,
    ): RedirectResponse {
        $targetId = $context->target()?->getAuthIdentifier();

        $impersonation->stop(ImpersonationEndReason::Stopped);

        if ($targetId === null) {
            return redirect()->route('home');
        }

        return redirect()->to(UserResource::getUrl('edit', ['record' => $targetId]));
    }
}
