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
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        assert($node instanceof Callout);

        $title = new HtmlElement('p', ['class' => 'kb-callout__title'], htmlspecialchars((string) __('knowledge.callouts.'.$node->type), ENT_QUOTES));

        return new HtmlElement(
            'aside',
            ['class' => 'kb-callout kb-callout--'.$node->type, 'role' => 'note'],
            $title."\n".$childRenderer->renderNodes($node->children())."\n",
        );
    }
}
