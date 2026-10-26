<?php

declare(strict_types=1);

namespace App\Notifications\Support;

/**
 * Mail lines are rendered as Markdown: user-controlled values (names, display names) must
 * not be able to open links, emphasis or HTML.
 *
 * Only Markdown punctuation is escaped. The mail template prints each line with Blade's
 * {{ }}, which already encodes & " ' < > as HTML entities (so no tag or <url> autolink can
 * form); a backslash before them would show up in the mail as a stray entity.
 */
final class EscapesMarkdown
{
    public static function escape(string $value): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|~])/', '\\\\$1', $value);
    }
}
