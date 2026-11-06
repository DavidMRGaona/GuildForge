<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering;

use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Infrastructure\Knowledge\Rendering\Extensions\ActionButtonExtension;
use App\Infrastructure\Knowledge\Rendering\Extensions\CalloutExtension;
use App\Infrastructure\Knowledge\Rendering\Extensions\PlaceholderExtension;
use App\Infrastructure\Knowledge\Rendering\RichContent\GuideRichContent;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown bodies (guide files) go through CommonMark; HTML bodies (the site's own guides, written in
 * the panel's rich editor) through GuideRichContent.
 */
final readonly class GuideMarkdownRenderer implements GuideRendererInterface
{
    public function __construct(
        private KnowledgePlaceholders $placeholders,
        private ActionLinkResolver $actions,
        private GuideRichContent $richContent,
    ) {}

    public function toHtml(Guide $guide, object $user): string
    {
        if ($guide->bodyFormat === GuideBodyFormat::Html) {
            return $this->richContent->toHtml($guide->body, $this->actions, $user, $guide->module);
        }

        return $this->convert($guide->body, $this->actions, $user, $guide->module);
    }

    public function toPlainText(string $body, GuideBodyFormat $format = GuideBodyFormat::Markdown): string
    {
        if ($format === GuideBodyFormat::Html) {
            return $this->richContent->toPlainText($body);
        }

        $html = $this->convert($body, null, null, null);
        // Space only at block boundaries: inline tags (strong, em, a) sit inside words and sentences
        $spaced = (string) preg_replace(
            '#(</(?:p|li|h[1-6]|td|th|tr|blockquote|aside|pre|ul|ol)>|<(?:br|hr)\s*/?>)#i',
            '$1 ',
            $html,
        );
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function convert(string $markdown, ?ActionLinkResolver $actions, ?object $user, ?string $module): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new CalloutExtension($actions instanceof ActionLinkResolver));
        $environment->addExtension(new ActionButtonExtension($actions, $user, $module));
        $environment->addExtension(new PlaceholderExtension($this->placeholders->values()));

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}
