<?php

declare(strict_types=1);

namespace App\Application\Seo;

enum SeoType: string
{
    case Website = 'website';
    case Article = 'article';
}
