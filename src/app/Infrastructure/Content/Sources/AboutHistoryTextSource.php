<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;

/**
 * The «Nosotros» history.
 */
final class AboutHistoryTextSource extends SettingsEditorTextSource
{
    public function type(): string
    {
        return 'about';
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageOrigin::About->folder();
    }

    protected function keys(): array
    {
        return ['about_history'];
    }
}
