<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Turns `> [!TIP]`, `> [!IMPORTANT]` and `> [!WARNING]` block quotes into callouts.
 */
final readonly class CalloutExtension implements ExtensionInterface
{
    /** @param bool $withTitle false for the plain-text path, where the translated title would pollute the index */
    public function __construct(private bool $withTitle = true) {}

    private const string MARKER = '/^\[!(TIP|IMPORTANT|WARNING)\]$/';

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, $this->process(...));
        $environment->addRenderer(Callout::class, new CalloutRenderer($this->withTitle));
    }

    private function process(DocumentParsedEvent $event): void
    {
        $quotes = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof BlockQuote) {
                $quotes[] = $node;
            }
        }

        foreach ($quotes as $quote) {
            $type = $this->markerType($quote);
            if ($type === null) {
                continue;
            }

            $callout = new Callout($type);
            foreach ($quote->children() as $child) {
                $callout->appendChild($child);
            }
            $quote->replaceWith($callout);
        }
    }

    /**
     * Detects the marker on the first line and removes it (with the line break after it).
     */
    private function markerType(BlockQuote $quote): ?string
    {
        $paragraph = $quote->firstChild();
        if (! $paragraph instanceof Paragraph) {
            return null;
        }

        $texts = [];
        $literal = '';
        for ($node = $paragraph->firstChild(); $node instanceof Text; $node = $node->next()) {
            $texts[] = $node;
            $literal .= $node->getLiteral();
            if (preg_match(self::MARKER, $literal, $matches) === 1) {
                foreach ($texts as $text) {
                    $text->detach();
                }
                $this->dropLeadingBreak($paragraph);

                return strtolower($matches[1]);
            }
        }

        return null;
    }

    private function dropLeadingBreak(Paragraph $paragraph): void
    {
        $first = $paragraph->firstChild();
        if ($first instanceof Node && ! $first instanceof Text && $first::class === Newline::class) {
            $first->detach();
        }
    }
}
