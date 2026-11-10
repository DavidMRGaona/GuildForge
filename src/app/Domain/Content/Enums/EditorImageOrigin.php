<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

use DateTimeInterface;

/**
 * Where an image inserted with a rich text editor comes from. Its file lives in
 * "<origin>/content/<year>/<month>", apart from the images of columns ("<origin>/<year>/<month>"),
 * so the media library never offers it for deletion (core#481).
 */
enum EditorImageOrigin: string
{
    case Events = 'events';
    case Articles = 'articles';
    case Knowledge = 'knowledge';
    case Legal = 'legal';
    case About = 'about';

    public const string FOLDER = 'content';

    public function directory(DateTimeInterface $month): string
    {
        return $this->value.'/'.self::FOLDER.'/'.$month->format('Y/m');
    }

    /**
     * The origin of a stored path "<origin>/content/<year>/<month>/<file>", or null for any other path.
     */
    public static function ofPath(string $path): ?self
    {
        if (preg_match('~^([a-z]+)/'.self::FOLDER.'/\d{4}/\d{2}/[^/]+$~', $path, $match) !== 1) {
            return null;
        }

        return self::tryFrom($match[1]);
    }
}
