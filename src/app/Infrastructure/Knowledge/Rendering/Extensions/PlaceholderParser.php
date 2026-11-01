<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Replaces known `{{marker}}` values with plain text nodes (escaped on output); unknown markers stay as written.
 */
final readonly class PlaceholderParser implements InlineParserInterface
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(private array $values) {}

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\{\{([a-z_]+)\}\}');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $name = $inlineContext->getSubMatches()[0];
        if (! array_key_exists($name, $this->values)) {
            return false;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new Text($this->values[$name]));

        return true;
    }
}
