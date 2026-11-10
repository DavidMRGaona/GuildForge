<?php

declare(strict_types=1);

namespace App\Application\Content\Contracts;

use App\Application\Content\DTOs\EditorTextDTO;
use App\Domain\Content\ValueObjects\EditorImageFolder;

/**
 * Stored texts written with a rich editor (core#496). Before core#481 every editor, of the core and of
 * the modules, stored its images at the root of the local "public" disk, so one local copy may be named
 * by texts of different sources (an image copied from an article to an announcement keeps its URL).
 * The move command reads every registered source: it moves the images of the texts and deletes a local
 * copy only once every text naming it, in any source, has been saved.
 *
 * The core registers its own texts; a module returns its sources from registerEditorTextSources().
 * App\Infrastructure\Content\Sources\TableEditorTextSource covers a text kept in one table column.
 */
interface EditorTextSourceInterface
{
    /**
     * Stable identifier, prefixed with the module (`announcements.content`) and unique across every source.
     */
    public function type(): string;

    /**
     * The content folder the images of these texts move to; the module's media source claims it.
     */
    public function folder(): EditorImageFolder;

    /**
     * Every stored text, soft-deleted records included (they can be restored). Throws when the texts
     * cannot be read: nothing must look unused because of it.
     *
     * @return iterable<EditorTextDTO>
     */
    public function texts(): iterable;

    /**
     * Writes $html over the text only if it still holds $text->html, so a save made meanwhile is never
     * lost. Moving an image is not an edit: no model events and no updated_at.
     *
     * @return bool whether it was written
     */
    public function replace(EditorTextDTO $text, string $html): bool;
}
