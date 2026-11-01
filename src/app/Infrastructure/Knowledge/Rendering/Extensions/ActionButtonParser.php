<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * `[[action:<route.name>|<Label>]]` and `[[action:<route.name>|<Label>|if=<condition>]]`.
 */
final class ActionButtonParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[action:([^\]|]+)\|([^\]|]+)(?:\|if=([^\]|]+))?\]\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $matches = $inlineContext->getSubMatches();
        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());

        $condition = isset($matches[2]) && trim($matches[2]) !== '' ? trim($matches[2]) : null;
        $inlineContext->getContainer()->appendChild(new ActionButton(trim($matches[0]), trim($matches[1]), $condition));

        return true;
    }
}
