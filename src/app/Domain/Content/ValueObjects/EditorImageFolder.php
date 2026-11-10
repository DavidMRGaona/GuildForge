<?php

declare(strict_types=1);

namespace App\Domain\Content\ValueObjects;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * The content folder of a scope, where the images inserted with a rich text editor live:
 * "<scope>/content/<year>/<month>/<file>". The scope is a core origin (EditorImageOrigin) or the
 * folder a module claims for its records ("announcements", "game-tables"). Kept apart from the
 * images of columns ("<scope>/<year>/<month>") so the source that reads the text claims them
 * (core#481, core#496).
 */
final readonly class EditorImageFolder
{
    public const string FOLDER = 'content';

    /** One lowercase segment: letters and digits, words joined by single hyphens */
    private const string SCOPE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    private function __construct(
        public string $scope,
    ) {}

    /**
     * @throws InvalidArgumentException when the scope is not one lowercase segment
     */
    public static function of(string $scope): self
    {
        if (preg_match('~^'.self::SCOPE_PATTERN.'$~', $scope) !== 1 || $scope === self::FOLDER) {
            throw new InvalidArgumentException('Not a folder scope for editor images: "'.$scope.'"');
        }

        return new self($scope);
    }

    /**
     * The folder of a stored path "<scope>/content/<year>/<month>/<file>", or null for any other path.
     */
    public static function ofPath(string $path): ?self
    {
        if (preg_match('~^('.self::SCOPE_PATTERN.')/'.self::FOLDER.'/\d{4}/\d{2}/[^/]+$~', $path, $match) !== 1 || $match[1] === self::FOLDER) {
            return null;
        }

        return new self($match[1]);
    }

    /**
     * Where an image inserted in the given month is stored, without a trailing slash.
     */
    public function directory(DateTimeInterface $month): string
    {
        return $this->scope.'/'.self::FOLDER.'/'.$month->format('Y/m');
    }

    /**
     * The folder a media source claims for these images, with its trailing slash.
     */
    public function claim(): string
    {
        return $this->scope.'/'.self::FOLDER.'/';
    }

    public function equals(self $other): bool
    {
        return $this->scope === $other->scope;
    }
}
