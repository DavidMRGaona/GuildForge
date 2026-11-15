<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * Who may see a public profile (core#739): anybody, signed-in accounts, the association's members or
 * nobody but its owner.
 */
enum ProfileVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Association = 'association';
    case Hidden = 'hidden';

    public static function default(): self
    {
        return self::Members;
    }

    /**
     * A stored value nobody knows (a later version, a manual edit) hides the profile: it fails closed.
     */
    public static function fromStored(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Hidden) : self::Hidden;
    }
}
