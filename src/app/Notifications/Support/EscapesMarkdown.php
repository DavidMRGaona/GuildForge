<?php

declare(strict_types=1);

namespace App\Notifications\Support;

/**
 * Mail lines are rendered as Markdown: user-controlled values (names, display names) must
 * not be able to open links, emphasis or HTML.
 */
final class EscapesMarkdown
{
    public static function escape(string $value): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~&"\'])/', '\\\\$1', $value);
    }
}
