<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Infrastructure\Mail\Preferences\MailSubjectTypeLabel;
use App\Infrastructure\Mail\Unsubscribe\SignedMailUnsubscribeLinks;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page a mail footer links to for muting one thing (`/correos/silenciar/{user}/{type}/{subject}`,
 * signed together with the names): opening it only asks to confirm, because spam filters open the
 * links of a mail; confirming mutes that thing for that account and the same address undoes it.
 *
 * The signature is the only credential: it never signs anyone in and acts on the account of the
 * link, whoever is signed in. It mutes optional mail only; mandatory mail is always sent.
 */
final class MailSubjectMuteController extends Controller
{
    public function __construct(
        private readonly MailSubjectMuteServiceInterface $mutes,
        private readonly MailUnsubscribeLinksInterface $links,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(Request $request, string $user, string $type, string $subject): Response
    {
        $account = $this->accountOrFail($user);
        $target = $this->subjectOrFail($request, $type, $subject);

        $response = Inertia::render('Mail/MuteSubject', [
            'subject' => [
                'label' => $target->label,
                'typeLabel' => MailSubjectTypeLabel::translate($target->typeLabel),
            ],
            'muted' => $this->mutes->isMuted($account, $target),
            'actionUrl' => $this->links->muteSubjectUrl($account->id, $target),
            'preferencesUrl' => $this->links->preferencesUrl(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.mail_mute_subject'),
                noindex: true,
            ))->toArray(),
        ])->toResponse($request);

        // The address carries the signature: keep it out of shared caches
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function store(Request $request, string $user, string $type, string $subject): RedirectResponse
    {
        $account = $this->accountOrFail($user);
        $target = $this->subjectOrFail($request, $type, $subject);

        $this->mutes->mute($account, $target);

        return redirect()->to($this->links->muteSubjectUrl($account->id, $target));
    }

    public function destroy(Request $request, string $user, string $type, string $subject): RedirectResponse
    {
        $account = $this->accountOrFail($user);
        $target = $this->subjectOrFail($request, $type, $subject);

        $this->mutes->unmute($account, $target->type, $target->id);

        return redirect()
            ->to($this->links->muteSubjectUrl($account->id, $target))
            ->with('success', __('mail.mute_subject.unmuted', ['subject' => $target->label]));
    }

    private function accountOrFail(string $user): UserModel
    {
        // Soft-deleted (deactivated) accounts are left out by the default scope
        $account = UserModel::query()->whereKey($user)->whereNull('anonymized_at')->first();

        abort_unless($account instanceof UserModel, Response::HTTP_NOT_FOUND);

        return $account;
    }

    /**
     * The signature already proves the site wrote these values; this only rebuilds them.
     */
    private function subjectOrFail(Request $request, string $type, string $subject): MailSubjectDTO
    {
        $label = $request->query(SignedMailUnsubscribeLinks::LABEL_PARAMETER);
        $typeLabel = $request->query(SignedMailUnsubscribeLinks::TYPE_LABEL_PARAMETER);

        abort_unless(is_string($label) && ($typeLabel === null || is_string($typeLabel)), Response::HTTP_NOT_FOUND);

        try {
            return new MailSubjectDTO($type, $subject, $label, $typeLabel);
        } catch (InvalidArgumentException) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
