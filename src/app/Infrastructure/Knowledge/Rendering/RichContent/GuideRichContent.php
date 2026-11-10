<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\RichContent;

use App\Infrastructure\Content\Services\EditorImageAttachmentProvider;
use App\Infrastructure\Knowledge\Rendering\ActionLinkResolver;
use App\Infrastructure\Knowledge\Rendering\KnowledgePlaceholders;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Bodies written in the panel's rich editor (the site's own guides): markers and action buttons
 * resolved, images pointed at Cloudinary, everything else sanitized by the renderer. Only the
 * button markup skips the sanitizer, and it is built here with every value escaped.
 */
final readonly class GuideRichContent
{
    /** Nodes that end a block of text: plain text puts a space after them */
    private const array BLOCK_NODES = ['paragraph', 'heading', 'listItem', 'blockquote', 'codeBlock', 'tableCell', 'tableHeader', 'hardBreak', 'horizontalRule', 'customBlock'];

    public function __construct(
        private KnowledgePlaceholders $placeholders,
        private EditorImageAttachmentProvider $images,
    ) {}

    public function toHtml(string $html, ActionLinkResolver $actions, object $user, ?string $module): string
    {
        return $this->renderer($html)
            ->customBlocks([
                ActionButtonBlock::class => ['resolver' => $actions, 'user' => $user, 'module' => $module],
            ])
            ->toHtml();
    }

    /**
     * Single-line text for the search index: markers resolved, button labels kept.
     */
    public function toPlainText(string $html): string
    {
        $text = $this->text($this->renderer($html)->toArray());

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function renderer(string $html): RichContentRenderer
    {
        return RichContentRenderer::make($html)
            ->mergeTags($this->placeholders->values())
            ->fileAttachmentProvider($this->images);
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function text(array $node): string
    {
        $type = $node['type'] ?? null;

        if ($type === 'text') {
            return is_string($node['text'] ?? null) ? $node['text'] : '';
        }

        if ($type === 'customBlock') {
            $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $config = is_array($attributes['config'] ?? null) ? $attributes['config'] : [];
            $label = ($attributes['id'] ?? null) === ActionButtonBlock::ID ? ActionButtonBlock::label($config) : null;

            return ($label ?? '').' ';
        }

        $text = '';
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $text .= $this->text($child);
            }
        }

        return in_array($type, self::BLOCK_NODES, true) ? $text.' ' : $text;
    }
}
