<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;

/**
 * Article contents.
 */
final class ArticleContentTextSource extends TableEditorTextSource
{
    public function type(): string
    {
        return 'articles';
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageOrigin::Articles->folder();
    }

    protected function table(): string
    {
        return 'articles';
    }

    protected function column(): string
    {
        return 'content';
    }
}
