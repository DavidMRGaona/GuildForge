<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Domain\Mail\ValueObjects\MutedMailSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UnmuteMailSubjectRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;

/**
 * «Volver a recibir» in the list of muted things of the «Avisos» tab. Like any signed-in route,
 * it stays closed to accounts that must still verify their email.
 */
final class MutedMailSubjectsController extends Controller
{
    public function __construct(
        private readonly MailSubjectMuteServiceInterface $mutes,
    ) {}

    public function destroy(UnmuteMailSubjectRequest $request): RedirectResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        $removed = $this->mutes->unmute($user, $request->subjectType(), $request->subjectId());

        // Already unmuted (another tab of the browser): nothing to say
        if (! $removed instanceof MutedMailSubject) {
            return back();
        }

        return back()->with('success', __('mail.mute_subject.unmuted', ['subject' => $removed->label]));
    }
}
