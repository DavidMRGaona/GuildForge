<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use HTMLPurifier_AttrDef_Enum;
use HTMLPurifier_Config;
use HTMLPurifier_CSSDefinition;
use Mews\Purifier\Facades\Purifier;

trait SanitizesHtml
{
    /**
     * The alignments the panel's RichEditor writes ("start" and "end" are the logical ones it uses
     * for left and right) plus the physical ones older texts may hold. HTMLPurifier only knows the
     * CSS 2 values, so the list replaces its own: still an exact list, never a free value.
     */
    private const array TEXT_ALIGNMENTS = ['left', 'right', 'center', 'justify', 'start', 'end'];

    protected function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $alignments = self::TEXT_ALIGNMENTS;

        // Not static, and nothing read through self: the purifier binds the hook to itself before
        // calling it. The definition is built for each clean, so the wider list lives only here;
        // without a definition the purifier's own list stays (start and end are dropped, never more)
        return Purifier::clean($html, 'richtext', function (HTMLPurifier_Config $config) use ($alignments): void {
            $definition = $config->getCSSDefinition();

            if ($definition instanceof HTMLPurifier_CSSDefinition) {
                $definition->info['text-align'] = new HTMLPurifier_AttrDef_Enum($alignments);
            }
        });
    }
}
