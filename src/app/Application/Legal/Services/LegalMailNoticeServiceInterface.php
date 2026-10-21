<?php

declare(strict_types=1);

namespace App\Application\Legal\Services;

use App\Application\Legal\DTOs\LegalEntityDTO;

interface LegalMailNoticeServiceInterface
{
    /**
     * Saved text of a notice or, when empty, its default; empty for an unregistered key.
     */
    public function content(string $key): string;

    /**
     * Notice split into paragraphs (blank lines), with the placeholders replaced.
     * Values are not escaped: whoever renders the email does that.
     *
     * @param  array<string, string>  $replacements  Placeholder (e.g. ':site') => value.
     * @return list<string>
     */
    public function paragraphs(string $key, array $replacements): array;

    /**
     * Data controller named in the notices, from the legal pages.
     */
    public function entity(): LegalEntityDTO;
}
