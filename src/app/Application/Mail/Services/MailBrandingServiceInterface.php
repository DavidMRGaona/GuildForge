<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\Response\MailBrandingDTO;

interface MailBrandingServiceInterface
{
    /**
     * Site name, logo, colors and fonts for email templates.
     *
     * Never throws: unreadable settings fall back to the default theme, so an
     * email is still sent.
     */
    public function branding(): MailBrandingDTO;
}
