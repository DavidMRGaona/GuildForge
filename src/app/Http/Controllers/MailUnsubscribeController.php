<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Infrastructure\Mail\Unsubscribe\MailUnsubscribeTarget;
use App\Infrastructure\Mail\Unsubscribe\MailUnsubscribeTargets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page a mail footer links to (`/correos/baja/{user}/{category}`, signed): opening it only
 * asks to confirm, because spam filters open the links of a mail; confirming turns that one
 * category off for that one account and the same address undoes it.
 *
 * The signature is the only credential: it never signs anyone in and acts on the account of the
 * link, whoever is signed in.
 */
final class MailUnsubscribeController extends Controller
{
    public function __construct(
        private readonly MailUnsubscribeTargets $targets,
        private readonly MailPreferenceServiceInterface $preferences,
        private readonly MailUnsubscribeLinksInterface $links,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function show(Request $request, string $user, string $category): Response
    {
        $target = $this->findOrFail($user, $category);
        $preference = $target->preference;

        $response = Inertia::render('Mail/Unsubscribe', [
            'category' => [
                'label' => $preference->label,
                'mandatory' => $preference->mandatory,
                'mandatoryReason' => $preference->mandatoryReason,
            ],
            'unsubscribed' => ! $preference->enabled,
            'actionUrl' => $this->links->unsubscribeUrl($target->user->id, $preference->key),
            'preferencesUrl' => $this->links->preferencesUrl(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.mail_unsubscribe'),
                noindex: true,
            ))->toArray(),
        ])->toResponse($request);

        // The address carries the signature: keep it out of shared caches
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function store(string $user, string $category): RedirectResponse
    {
        $target = $this->findOrFail($user, $category);
        $this->change($target, false);

        return redirect()->to($this->links->unsubscribeUrl($target->user->id, $target->preference->key));
    }

    public function destroy(string $user, string $category): RedirectResponse
    {
        $target = $this->findOrFail($user, $category);
        $this->change($target, true);

        return redirect()
            ->to($this->links->unsubscribeUrl($target->user->id, $target->preference->key))
            ->with('success', __('mail.unsubscribe.resubscribed', ['category' => $target->preference->label]));
    }

    private function findOrFail(string $user, string $category): MailUnsubscribeTarget
    {
        $target = $this->targets->find($user, $category);

        abort_unless($target instanceof MailUnsubscribeTarget, Response::HTTP_NOT_FOUND);

        return $target;
    }

    private function change(MailUnsubscribeTarget $target, bool $enabled): void
    {
        // A mandatory category is always sent: a link never turns it off
        abort_if($target->preference->mandatory, Response::HTTP_UNPROCESSABLE_ENTITY);

        try {
            $this->preferences->update($target->user, [$target->preference->key => $enabled]);
        } catch (MailPreferenceNotEditableException) {
            // The category went away or stopped being offered after the page was opened
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
