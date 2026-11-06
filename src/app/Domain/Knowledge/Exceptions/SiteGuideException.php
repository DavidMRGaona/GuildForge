<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exceptions;

use DomainException;

/**
 * A site guide (written in the panel) that cannot be stored as asked.
 */
final class SiteGuideException extends DomainException
{
    public static function notASiteGuide(string $id): self
    {
        return new self(sprintf("'%s' is not one of the site's own guides.", $id));
    }

    public static function notAnOfficialGuide(string $id): self
    {
        return new self(sprintf("'%s' is not an official guide.", $id));
    }

    public static function missingText(string $field): self
    {
        return new self(sprintf("The guide's %s cannot be empty.", $field));
    }

    public static function textTooLong(string $field, int $max): self
    {
        return new self(sprintf("The guide's %s cannot be longer than %d characters.", $field, $max));
    }

    public static function unknownCategory(string $category, string $audience): self
    {
        return new self(sprintf("There is no '%s' category for the %s audience.", $category, $audience));
    }

    public static function unknownCondition(string $kind, string $key): self
    {
        return new self(sprintf("Unknown %s in the guide's conditions: '%s'.", $kind, $key));
    }

    public static function invalidRelated(string $id): self
    {
        return new self(sprintf("'%s' cannot be a related guide.", $id));
    }
}
