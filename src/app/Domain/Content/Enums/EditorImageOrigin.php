<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

use App\Domain\Content\ValueObjects\EditorImageFolder;
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

    public const string FOLDER = EditorImageFolder::FOLDER;

    public function folder(): EditorImageFolder
    {
        return EditorImageFolder::of($this->value);
    }

    public function directory(DateTimeInterface $month): string
    {
        return $this->folder()->directory($month);
    }

    /**
     * The origin of a stored path "<origin>/content/<year>/<month>/<file>", or null for any other path.
     */
    public static function ofPath(string $path): ?self
    {
        $folder = EditorImageFolder::ofPath($path);

        return $folder instanceof EditorImageFolder ? self::tryFrom($folder->scope) : null;
    }
}
