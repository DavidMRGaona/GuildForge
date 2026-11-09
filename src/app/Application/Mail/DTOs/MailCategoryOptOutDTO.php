<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs;

/**
 * How many accounts turned off one optional mail category, out of those that can receive it.
 * Only counts: never who.
 */
final readonly class MailCategoryOptOutDTO
{
    /**
     * @param  string  $section  Translated heading the category is listed under.
     * @param  int  $optedOut  Active accounts that turned the category off.
     * @param  int  $eligible  Active accounts that can receive it.
     * @param  float  $percentage  optedOut over eligible, one decimal; 0 when nobody is eligible.
     */
    public function __construct(
        public string $key,
        public string $section,
        public string $label,
        public int $optedOut,
        public int $eligible,
        public float $percentage,
    ) {}
}
