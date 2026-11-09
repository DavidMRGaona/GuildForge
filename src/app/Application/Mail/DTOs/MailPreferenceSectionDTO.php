<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

/**
 * The categories a person sees under one heading («Cuenta», a module name...).
 */
final readonly class MailPreferenceSectionDTO
{
    /**
     * @param  string  $key  The section as the categories declare it (its translation key).
     * @param  list<MailPreferenceDTO>  $categories  In registration order.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $categories,
    ) {}
}
