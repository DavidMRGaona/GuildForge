<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum GuideBodyFormat: string
{
    case Markdown = 'markdown';
    case Html = 'html';
}
