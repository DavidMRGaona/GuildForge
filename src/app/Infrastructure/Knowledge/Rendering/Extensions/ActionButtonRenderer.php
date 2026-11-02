<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use App\Infrastructure\Knowledge\Rendering\ActionLinkResolver;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

/**
 * Renders the button as a link, or nothing when it cannot be resolved for the user.
 * Without a resolver (plain text) it keeps the label only.
 */
final readonly class ActionButtonRenderer implements NodeRendererInterface
{
    public function __construct(
        private ?ActionLinkResolver $resolver,
        private ?object $user,
        private ?string $module,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string
    {
        assert($node instanceof ActionButton);

        $label = htmlspecialchars($node->label, ENT_QUOTES);
        if (! $this->resolver instanceof ActionLinkResolver || $this->user === null) {
            return $label;
        }

        $url = $this->resolver->resolve($node->route, $node->condition, $this->module, $this->user);

        return $url === null ? '' : new HtmlElement('a', ['class' => 'kb-action', 'href' => $url], $label);
    }
}
