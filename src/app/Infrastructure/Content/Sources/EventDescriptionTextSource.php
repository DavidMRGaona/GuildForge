<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;

/**
 * Event descriptions.
 */
final class EventDescriptionTextSource extends TableEditorTextSource
{
    public function type(): string
    {
        return 'events';
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageOrigin::Events->folder();
    }

    protected function table(): string
    {
        return 'events';
    }

    protected function column(): string
    {
        return 'description';
    }
}
