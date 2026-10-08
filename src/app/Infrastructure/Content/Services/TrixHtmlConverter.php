<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\Services\TrixHtmlConverterInterface;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use RuntimeException;

/**
 * Trix (Filament 3) wrote blocks as <p>, a blank line as <p><br></p>, strikethrough as <del>,
 * quotes with bare text, uploads as <p><figure data-trix-attachment><a><img><figcaption></a></figure></p>
 * and two or more images of a block as <div class="attachment-gallery"><figure>...</figure>...</div>;
 * older Trix content used <div> blocks (paragraphs split by <br><br>) and an <a> around the
 * figure. TipTap drops the figure and its caption, so the Trix markup is rewritten first; the
 * result then goes through the same TipTap editor the panel uses, which makes it a fixed point
 * of the editor. Attachment URLs are kept verbatim: files are never fetched.
 */
final class TrixHtmlConverter implements TrixHtmlConverterInterface
{
    private const int MAX_EDITOR_PASSES = 3;

    /** A <figure> and its content: Trix never nests figures, and attribute values may hold ">" */
    private const string FIGURE = '~(<figure\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>).*?</figure>~is';

    private const string SLOT = 'data-trix-attachment-slot';

    /** Elements that end a run of inline content */
    private const array BLOCKS = [
        'address', 'blockquote', 'div', 'dl', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'ol', 'p', 'pre', 'table', 'ul',
    ];

    public function convert(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $converted = $this->rewriteTrixMarkup($html);

        // The panel parses and serialises the content on load and again on save
        for ($pass = 0; $pass < self::MAX_EDITOR_PASSES; $pass++) {
            $next = $this->throughEditor($converted);

            if ($next === $converted) {
                break;
            }

            $converted = $next;
        }

        return $converted;
    }

    /**
     * What the panel's rich editor stores for this HTML: Filament's TipTap editor, plus the
     * paragraph Filament wraps around list items that start with text (RichEditorStateCast).
     */
    private function throughEditor(string $html): string
    {
        return RichContentRenderer::make()
            ->getEditor()
            ->setContent($html)
            ->descendants(static function (object &$node): void {
                if (! isset($node->type, $node->content) || $node->type !== 'listItem' || ! is_array($node->content)) {
                    return;
                }

                if (($node->content[0]->type ?? null) !== 'text') {
                    return;
                }

                $node->content = [(object) ['type' => 'paragraph', 'content' => $node->content]];
            })
            ->getHtml();
    }

    private function rewriteTrixMarkup(string $html): string
    {
        // An HTML parser closes the <p> before a <figure> and opens an empty one after it, so
        // each attachment waits in an inline slot while the rest of the markup is parsed
        $attachments = [];
        $html = preg_replace_callback(self::FIGURE, static function (array $match) use (&$attachments): string {
            if (stripos($match[1], 'data-trix-attachment') === false) {
                return $match[0];
            }

            $attachments[] = $match[0];

            return '<span '.self::SLOT.'="'.(count($attachments) - 1).'"></span>';
        }, $html) ?? throw new RuntimeException('Could not read the Trix attachments: '.preg_last_error_msg());

        $document = $this->parse($html);
        $body = $document->body;

        if ($body === null) {
            return $html;
        }

        // A gallery (two or more images in a block) shows one image per row
        foreach ($body->querySelectorAll('.attachment-gallery') as $gallery) {
            $this->splitGallery($document, $gallery);
        }

        foreach ($body->querySelectorAll('['.self::SLOT.']') as $slot) {
            $figure = $this->parse($attachments[(int) $slot->getAttribute(self::SLOT)] ?? '')->body?->firstElementChild;

            if ($figure === null) {
                $slot->remove();

                continue;
            }

            $this->replaceAttachment($document, $slot, $figure);
        }

        foreach ($body->querySelectorAll('del') as $deleted) {
            $this->rename($document, $deleted, 's');
        }

        foreach ($body->querySelectorAll('div') as $block) {
            $this->replaceBlock($document, $block);
        }

        // TipTap's quote holds paragraphs; Trix wrote its text straight inside
        foreach ($body->querySelectorAll('blockquote') as $quote) {
            $this->wrapInlineContent($document, $quote);
        }

        $this->joinLines($body);

        return $body->innerHTML;
    }

    private function parse(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>'.$html.'</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );
    }

    /**
     * Images become an <img> with the caption as alt text and, below it, in italics;
     * other files become a link named after the file.
     */
    private function replaceAttachment(HTMLDocument $document, Element $slot, Element $attachment): void
    {
        /** @var array{url?: string, href?: string, filename?: string, caption?: string} $data */
        $data = json_decode((string) $attachment->getAttribute('data-trix-attachment'), true) ?: [];
        /** @var array{caption?: string} $attributes */
        $attributes = json_decode((string) $attachment->getAttribute('data-trix-attributes'), true) ?: [];
        $image = $attachment->querySelector('img');
        $caption = $this->caption($attachment, $attributes + $data);
        $replacement = $document->createDocumentFragment();

        if ($image !== null) {
            $img = $document->createElement('img');
            $img->setAttribute('src', (string) ($image->getAttribute('src') ?: ($data['url'] ?? '')));
            $img->setAttribute('alt', $caption !== '' ? $caption : (string) $image->getAttribute('alt'));
            $replacement->append($img);

            if ($caption !== '') {
                $emphasis = $document->createElement('em');
                $emphasis->textContent = $caption;
                $replacement->append($document->createElement('br'), $emphasis);
            }
        } else {
            $link = $document->createElement('a');
            $link->setAttribute('href', (string) ($data['href'] ?? $data['url'] ?? $attachment->querySelector('a')?->getAttribute('href') ?? ''));
            $link->textContent = $caption !== '' ? $caption : (string) ($data['filename'] ?? $data['url'] ?? '');
            $replacement->append($link);
        }

        // Older Trix content wraps a linked attachment in an <a> of its own
        $wrapper = $slot->parentElement;
        $target = $wrapper !== null && $wrapper->localName === 'a' && $wrapper->childNodes->length === 1 ? $wrapper : $slot;

        // Text after a captioned image starts on a line of its own
        if ($caption !== '' && $image !== null && trim((string) $target->nextSibling?->textContent) !== '') {
            $replacement->append($document->createElement('br'));
        }

        // An attachment outside any block gets a paragraph of its own
        if ($target->parentElement?->localName === 'body') {
            $paragraph = $document->createElement('p');
            $paragraph->append($replacement);
            $replacement = $paragraph;
        }

        $target->replaceWith($replacement);
    }

    /**
     * A caption typed by the editor; Trix's own "name and size" caption is not one.
     *
     * @param  array{caption?: string}  $data
     */
    private function caption(Element $attachment, array $data): string
    {
        if (isset($data['caption']) && trim($data['caption']) !== '') {
            return trim($data['caption']);
        }

        $figcaption = $attachment->querySelector('figcaption');

        if ($figcaption === null || $figcaption->querySelector('.attachment__name') !== null) {
            return '';
        }

        return trim((string) $figcaption->textContent);
    }

    private function splitGallery(HTMLDocument $document, Element $gallery): void
    {
        $replacement = $document->createDocumentFragment();

        foreach (iterator_to_array($gallery->childNodes) as $node) {
            if ($node instanceof Text && trim($node->data) === '') {
                continue;
            }

            $paragraph = $document->createElement('p');
            $paragraph->append($node);
            $replacement->append($paragraph);
        }

        $gallery->replaceWith($replacement);
    }

    /**
     * A <div> block (older Trix content) becomes one paragraph per run of text between
     * <br><br>; a block with no text is Trix's blank line and stays one: <p><br></p>.
     */
    private function replaceBlock(HTMLDocument $document, Element $block): void
    {
        if ($block->parentNode === null) {
            return;
        }

        $paragraphs = [];
        $current = [];
        $pendingBreak = null;

        foreach (iterator_to_array($block->childNodes) as $node) {
            if ($node instanceof Element && $node->localName === 'br') {
                if ($pendingBreak !== null) {
                    $paragraphs[] = $current;
                    $current = [];
                    $pendingBreak = null;

                    continue;
                }

                $pendingBreak = $node;

                continue;
            }

            if ($pendingBreak !== null) {
                $current[] = $pendingBreak;
                $pendingBreak = null;
            }

            $current[] = $node;
        }

        $paragraphs[] = $current;

        // <br><br> closing a block ends the paragraph, it does not open an empty one
        $paragraphs = array_values(array_filter($paragraphs, static fn (array $nodes): bool => $nodes !== []))
            ?: [[$document->createElement('br')]];
        $replacement = $document->createDocumentFragment();

        foreach ($paragraphs as $nodes) {
            $paragraph = $document->createElement('p');

            foreach ($nodes as $node) {
                $paragraph->append($node);
            }

            $replacement->append($paragraph);
        }

        $block->replaceWith($replacement);
    }

    /**
     * Every run of inline content between the blocks of $container goes into a paragraph.
     */
    private function wrapInlineContent(HTMLDocument $document, Element $container): void
    {
        $run = [];

        foreach (iterator_to_array($container->childNodes) as $node) {
            if ($node instanceof Element && in_array($node->localName, self::BLOCKS, true)) {
                $this->wrapRun($document, $container, $run, $node);
                $run = [];

                continue;
            }

            $run[] = $node;
        }

        $this->wrapRun($document, $container, $run, null);
    }

    /**
     * @param  list<Node>  $run
     */
    private function wrapRun(HTMLDocument $document, Element $container, array $run, ?Node $before): void
    {
        $blank = array_filter($run, static fn (Node $node): bool => ! $node instanceof Text || trim($node->data) !== '') === [];

        if ($blank) {
            return;
        }

        $paragraph = $document->createElement('p');
        $paragraph->append(...$run);
        $container->insertBefore($paragraph, $before);
    }

    /**
     * A line break between words shows as a space, but the editor deletes it and glues the
     * words together (text written outside the editor, such as seeded content); <pre> keeps it.
     */
    private function joinLines(Element $element): void
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof Text) {
                $child->data = preg_replace('~(?<=\S)\s*\R\s*(?=\S)~u', ' ', $child->data) ?? $child->data;
            } elseif ($child instanceof Element && $child->localName !== 'pre') {
                $this->joinLines($child);
            }
        }
    }

    private function rename(HTMLDocument $document, Element $element, string $name): void
    {
        $renamed = $document->createElement($name);

        foreach (iterator_to_array($element->childNodes) as $child) {
            $renamed->append($child);
        }

        $element->replaceWith($renamed);
    }
}
