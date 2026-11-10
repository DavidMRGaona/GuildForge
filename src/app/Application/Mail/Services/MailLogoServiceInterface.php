<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\Response\MailLogoPngDTO;

/**
 * The logo mails link: the email logo, or the light logo when there is none, in a format
 * mail clients show. Gmail and Outlook show neither SVG nor (Outlook) WebP, so those are
 * linked as a PNG instead.
 */
interface MailLogoServiceInterface
{
    /**
     * Absolute URL for the mail header, or null when there is no logo or it cannot be shown
     * (a mail without logo is better than a broken image).
     */
    public function url(): ?string;

    /**
     * The SVG logo drawn as a PNG, which the site serves at the URL above; null when the
     * logo is not an SVG or cannot be drawn.
     */
    public function png(): ?MailLogoPngDTO;
}
