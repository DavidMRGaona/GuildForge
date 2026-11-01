<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

final readonly class CalloutRenderer implements NodeRendererInterface
{
    public function __construct(private bool $withTitle = true) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        assert($node instanceof Callout);

        $title = $this->withTitle
            ? new HtmlElement('p', ['class' => 'kb-callout__title'], htmlspecialchars((string) __('knowledge.callouts.'.$node->type), ENT_QUOTES))."\n"
            : '';

        return new HtmlElement(
            'aside',
            ['class' => 'kb-callout kb-callout--'.$node->type, 'role' => 'note'],
            $title.$childRenderer->renderNodes($node->children())."\n",
        );
    }
}
