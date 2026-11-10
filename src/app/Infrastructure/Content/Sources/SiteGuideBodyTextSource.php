<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;
use App\Domain\Knowledge\Enums\GuideSource;
use Illuminate\Database\Query\Builder;

/**
 * The body of the site's own guides. Official guides come from Markdown files, not from the editor.
 */
final class SiteGuideBodyTextSource extends TableEditorTextSource
{
    public function type(): string
    {
        return 'guides';
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageOrigin::Knowledge->folder();
    }

    protected function table(): string
    {
        return 'knowledge_guides';
    }

    protected function column(): string
    {
        return 'body';
    }

    protected function scope(Builder $query): Builder
    {
        return $query->where('source', GuideSource::Site->value);
    }
}
