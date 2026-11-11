<?php

declare(strict_types=1);

namespace Tests\Support\Content;

use App\Domain\Content\ValueObjects\EditorImageFolder;
use App\Infrastructure\Content\Sources\TableEditorTextSource;

/**
 * Another class claiming the type of NotesEditorTextSource.
 */
final class DuplicateNotesEditorTextSource extends TableEditorTextSource
{
    public function type(): string
    {
        return NotesEditorTextSource::TYPE;
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageFolder::of('duplicate');
    }

    protected function table(): string
    {
        return 'duplicates';
    }

    protected function column(): string
    {
        return 'body';
    }
}
