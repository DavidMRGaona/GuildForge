<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs\Response;

/**
 * Site identity and theme values for email templates.
 *
 * Colors are normalized hex values, fonts are sanitized CSS font stacks and
 * the radius is a pixel length, so templates may print them unescaped in CSS.
 *
 * No web font is loaded: the stacks name the theme font, used when the reader's
 * device has it, so opening an email never asks a third party for anything.
 */
final readonly class MailBrandingDTO
{
    public function __construct(
        public string $siteName,
        public string $siteUrl,
        public ?string $logoUrl,
        public string $primaryColor,
        public string $onPrimaryColor,
        public string $linkColor,
        public string $panelColor,
        public string $panelBorderColor,
        public string $backgroundColor,
        public string $surfaceColor,
        public string $headingColor,
        public string $textColor,
        public string $mutedColor,
        public string $borderColor,
        public string $fontHeading,
        public string $fontBody,
        public string $borderRadius,
    ) {}
}
