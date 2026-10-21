<?php

declare(strict_types=1);

namespace App\Application\Legal\DTOs;

use Closure;

/**
 * A legal notice a module puts in one of its emails, editable from the legal pages.
 */
final readonly class LegalMailNoticeDTO
{
    /**
     * @param  list<string>  $placeholders  Placeholders the notice can use, e.g. ':inviter'.
     * @param  Closure(string): string  $preview  Receives the notice text and returns the whole email as HTML, with sample data.
     */
    public function __construct(
        public string $key,
        public string $module,
        public string $label,
        public string $help,
        public string $defaultContent,
        public array $placeholders,
        public Closure $preview,
    ) {}
}
