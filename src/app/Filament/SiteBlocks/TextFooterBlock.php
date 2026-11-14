<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Content\Services\RichTextSanitizerInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Filament\Forms\RichEditor\EditorImages;
use App\Infrastructure\Content\Sources\FooterTextSource;
use Filament\Forms\Components\RichEditor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A rich text. Its images go to the footer's editor folder (`footer/content/…`) and its text is an
 * editor text source of its own (FooterTextSource), which rewrites only this block inside the JSON.
 */
final class TextFooterBlock extends CoreFooterBlock
{
    public const string CONTENT_FIELD = FooterTextSource::CONTENT_FIELD;

    public function __construct(
        private readonly RichTextSanitizerInterface $sanitizer,
    ) {}

    public function key(): string
    {
        return FooterTextSource::BLOCK_KEY;
    }

    public function label(): string
    {
        return __('filament.footer.blocks.text.label');
    }

    public function settingsSchema(): array
    {
        return [
            EditorImages::configure(
                RichEditor::make(self::CONTENT_FIELD)
                    ->label(__('filament.footer.blocks.text.content'))
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'link'],
                        ['bulletList', 'orderedList'],
                        ['attachFiles'],
                        ['undo', 'redo'],
                    ])
                    ->columnSpanFull(),
                EditorImageOrigin::Footer,
            ),
        ];
    }

    public function component(): string
    {
        return 'components/siteBlocks/TextBlock.vue';
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        $html = $settings[self::CONTENT_FIELD] ?? null;

        return [
            'html' => is_string($html) && $html !== '' ? $this->sanitizer->sanitize($html, EditorImageOrigin::Footer) : '',
        ];
    }
}
