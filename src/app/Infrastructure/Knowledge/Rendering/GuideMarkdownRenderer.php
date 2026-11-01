<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering;

use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Infrastructure\Knowledge\Rendering\Extensions\ActionButtonExtension;
use App\Infrastructure\Knowledge\Rendering\Extensions\CalloutExtension;
use App\Infrastructure\Knowledge\Rendering\Extensions\PlaceholderExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

final readonly class GuideMarkdownRenderer implements GuideRendererInterface
{
    public function __construct(
        private KnowledgePlaceholders $placeholders,
        private ActionLinkResolver $actions,
    ) {}

    public function toHtml(Guide $guide, object $user): string
    {
        return $this->convert($guide->body, $this->actions, $user, $guide->module);
    }

    public function toPlainText(string $markdown): string
    {
        $html = $this->convert($markdown, null, null, null);
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5);

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
