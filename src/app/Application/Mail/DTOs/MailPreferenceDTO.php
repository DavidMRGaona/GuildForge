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
        /** Always true for a mandatory category. */
        public bool $enabled,
    ) {}
}
