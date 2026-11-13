<?php

declare(strict_types=1);

namespace App\Domain\SiteNews;

/**
 * The core content whose first publication is news for every account (core#613).
 */
enum SiteNewsKind: string
{
    case Event = 'event';
    case Article = 'article';
    case Gallery = 'gallery';

    /**
     * The mail subject type of its notices, so deleting the content forgets them.
     */
    public function subjectType(): string
    {
        return 'core.'.$this->value;
    }

    /**
     * Lucide icon of its notice in the bell.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Event => 'calendar-days',
            self::Article => 'newspaper',
            self::Gallery => 'images',
        };
    }
}
