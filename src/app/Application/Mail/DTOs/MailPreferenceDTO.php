<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

/**
 * One mail category as a person sees it, with the texts already translated.
 */
final readonly class MailPreferenceDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public bool $mandatory,
        public ?string $mandatoryReason,
        /** Always true for a mandatory category; false only when the person receives none of it. */
        public bool $enabled,
        /** Whether it can be received as a daily digest (core#333). */
        public bool $digestible = false,
        /** "immediate", "digest" or "none" (MailDelivery); "immediate" for a mandatory category. */
        public string $delivery = 'immediate',
        /** Whether it is sent by mail at all; false for a category shown only on the site (core#491). */
        public bool $mail = true,
        /** Whether it also appears in the notification centre (core#491). */
        public bool $site = false,
        /** Whether the person sees its notices there; always true for a mandatory one, false without `site`. */
        public bool $siteEnabled = false,
    ) {}
}
