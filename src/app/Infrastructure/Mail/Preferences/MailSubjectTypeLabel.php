<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

/**
 * Translates the stored kind of a muted thing («Campaña»). A key nobody translates any more (its
 * module is disabled or gone) gives null, so only the stored name is shown.
 */
final class MailSubjectTypeLabel
{
    public static function translate(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $text = __($key);

        return is_string($text) && $text !== '' && $text !== $key ? $text : null;
    }
}
