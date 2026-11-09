<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;
use App\Infrastructure\Mail\Unsubscribe\MailUnsubscribeTarget;
use App\Infrastructure\Mail\Unsubscribe\MailUnsubscribeTargets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * The RFC 8058 address of the List-Unsubscribe header (`/correos/baja/{user}/{category}/un-clic`,
 * signed apart from the page). The mail client posts to it with no session, cookies or CSRF token,
 * so the route runs without them; the signature and the rate limit protect it. It is the only
 * route of the site outside CSRF protection.
 */
final class OneClickMailUnsubscribeController extends Controller
{
    public function __construct(
        private readonly MailUnsubscribeTargets $targets,
        private readonly MailPreferenceServiceInterface $preferences,
        private readonly MailUnsubscribeLinksInterface $links,
    ) {}

    public function store(string $user, string $category): Response
    {
        $target = $this->targets->find($user, $category);

        if (! $target instanceof MailUnsubscribeTarget) {
            return $this->plain(__('mail.unsubscribe.one_click_not_found'), HttpStatus::HTTP_NOT_FOUND);
        }

        $preference = $target->preference;

        if ($preference->mandatory) {
            return $this->plain(__('mail.unsubscribe.mandatory', ['category' => $preference->label]), HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->preferences->update($target->user, [$preference->key => false]);
        } catch (MailPreferenceNotEditableException) {
            return $this->plain(__('mail.unsubscribe.one_click_not_found'), HttpStatus::HTTP_NOT_FOUND);
        }

        return $this->plain(__('mail.unsubscribe.done', ['category' => $preference->label]), HttpStatus::HTTP_OK);
    }

    /**
     * A client that does not speak RFC 8058 may open the address in the browser: opening never
     * unsubscribes, so it only leads to the confirmation page.
     */
    public function show(string $user, string $category): RedirectResponse
    {
        return redirect()->to($this->links->unsubscribeUrl($user, $category));
    }

    /**
     * @param  string|array<mixed>  $text
     */
    private function plain(string|array $text, int $status): Response
    {
        return response(is_string($text) ? $text : '', $status, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
