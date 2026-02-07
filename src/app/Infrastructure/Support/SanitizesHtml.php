<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use Mews\Purifier\Facades\Purifier;

trait SanitizesHtml
{
    protected function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return Purifier::clean($html, 'richtext');
    }
}
