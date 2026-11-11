<?php

declare(strict_types=1);

namespace App\Application\Content\DTOs;

/**
 * One stored text of an editor text source, as it was read.
 */
final readonly class EditorTextDTO
{
    /**
     * @param  string  $id  What the source needs to write it back (the record key, a settings key…)
     * @param  string  $html  The stored text
     * @param  string  $label  How the move report names it ("articles.content#<id>")
     */
    public function __construct(
        public string $id,
        public string $html,
        public string $label,
    ) {}
}
