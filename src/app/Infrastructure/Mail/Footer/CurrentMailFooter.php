<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Footer;

use App\Application\Mail\DTOs\MailFooterDTO;

/**
 * The footer of the mail being rendered right now.
 *
 * `<x-mail::message>` is an anonymous component and does not see the data of the view that uses
 * it, so the mail channel leaves the footer here while it renders and a view composer hands it
 * to the template. A singleton, not scoped: the notification channel manager caches the mail
 * channel across queued jobs, and both must see the same instance. The channel always puts back
 * what was here before, so nothing leaks into the next mail.
 */
final class CurrentMailFooter
{
    private ?MailFooterDTO $footer = null;

    public function current(): ?MailFooterDTO
    {
        return $this->footer;
    }

    public function set(?MailFooterDTO $footer): void
    {
        $this->footer = $footer;
    }
}
