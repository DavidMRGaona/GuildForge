<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Sources;

use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;

/**
 * The texts of the legal pages.
 */
final class LegalPagesTextSource extends SettingsEditorTextSource
{
    public function type(): string
    {
        return 'legal';
    }

    public function folder(): EditorImageFolder
    {
        return EditorImageOrigin::Legal->folder();
    }

    protected function keys(): array
    {
        return ['legal_privacy_content', 'legal_notice_content', 'legal_cookies_content', 'legal_terms_content'];
    }
}
