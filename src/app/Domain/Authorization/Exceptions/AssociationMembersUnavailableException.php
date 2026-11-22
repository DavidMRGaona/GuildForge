<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use RuntimeException;

/**
 * Who among some accounts is an association member could not be decided (core#824): a reader the answer
 * depends on failed. Never answered as «nobody»: a caller that cannot tell must not take it for an answer.
 */
final class AssociationMembersUnavailableException extends RuntimeException
{
    public static function twoFactorUnreadable(): self
    {
        return new self('The two-factor status needed to decide who is an association member could not be read.');
    }
}
