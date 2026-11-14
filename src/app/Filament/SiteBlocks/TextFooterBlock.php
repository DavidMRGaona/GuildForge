<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Content\Services\RichTextSanitizerInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Filament\Forms\RichEditor\EditorImages;
use App\Infrastructure\Content\Sources\FooterTextSource;
use Filament\Forms\Components\RichEditor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Throwable;

/**
 * A rich text. Its images go to the footer's editor folder (`footer/content/…`) and its text is an
 * editor text source of its own (FooterTextSource), which rewrites only this block inside the JSON.
 */
final class TextFooterBlock extends CoreFooterBlock
{
    public const string CONTENT_FIELD = FooterTextSource::CONTENT_FIELD;

    /** Bump it when the sanitizing rules change, so no text cleaned by the old ones is served */
    public const string CACHE_PREFIX = 'footer:text:v1:';

    /** Seconds: an edited text gets a new hash at once, the old entry just expires */
    public const int CACHE_TTL = 7 * 24 * 3600;

    public function __construct(
        private readonly RichTextSanitizerInterface $sanitizer,
        private readonly Cache $cache,
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
            'html' => is_string($html) && $html !== '' ? $this->clean($html) : '',
        ];
    }

    /**
     * The sanitized text, kept in the cache by the hash of what is stored: the footer is on every page
     * and HTMLPurifier is slow, but the stored text is never trusted as it is (it can be written outside
     * the panel). A cache that fails only costs sanitizing again.
     */
    private function clean(string $html): string
    {
        $sanitize = fn (): string => $this->sanitizer->sanitize($html, EditorImageOrigin::Footer);

        try {
            return (string) $this->cache->remember(self::CACHE_PREFIX.hash('sha256', $html), self::CACHE_TTL, $sanitize);
        } catch (Throwable) {
            return $sanitize();
        }
    }
}
