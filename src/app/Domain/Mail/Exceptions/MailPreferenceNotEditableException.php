<?php

declare(strict_types=1);

namespace App\Domain\Mail\Exceptions;

use DomainException;

/**
 * A person tried to change a mail category they cannot change: unknown, mandatory or not
 * shown to them, or asked for a digest the category does not offer. Nothing is saved.
 */
final class MailPreferenceNotEditableException extends DomainException
{
    private function __construct(
        string $message,
        public readonly string $categoryKey,
    ) {
        parent::__construct($message);
    }

    public static function unknown(string $key): self
    {
        return new self(sprintf('The mail category "%s" does not exist.', $key), $key);
    }

    public static function mandatory(string $key): self
    {
        return new self(sprintf('The mail category "%s" is always sent.', $key), $key);
    }

    public static function notVisible(string $key): self
    {
        return new self(sprintf('The mail category "%s" is not offered to this person.', $key), $key);
    }

    public static function notByMail(string $key): self
    {
        return new self(sprintf('The mail category "%s" is not sent by mail.', $key), $key);
    }

    public static function notOnSite(string $key): self
    {
        return new self(sprintf('The mail category "%s" has no notices on the site.', $key), $key);
    }

    public static function notDigestible(string $key): self
    {
        return new self(sprintf('The mail category "%s" cannot be received as a daily digest.', $key), $key);
    }
}
