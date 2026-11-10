<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PauseMailRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pauses and resumes the optional mail of the signed-in person (the «Avisos» tab of the
 * profile). Like any signed-in route, it stays closed to accounts that must still verify their
 * email.
 */
final class MailPauseController extends Controller
{
    public function __construct(
        private readonly MailPauseServiceInterface $pauses,
    ) {}

    public function update(PauseMailRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $this->pauses->pause($user, $request->duration());

        return back()->with('success', __('auth.mail_pause_started'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $this->pauses->resume($user);

        return back()->with('success', __('auth.mail_pause_ended'));
    }
}
