<?php

declare(strict_types=1);

namespace App\Domain\Mail\Enums;

/**
 * How a person receives an optional mail category (core#333). Only categories their module marks
 * as digestible offer Digest; the others are Immediate or None, the on and off of the switch.
 */
enum MailDelivery: string
{
    /** Each mail when it happens (the default). */
    case Immediate = 'immediate';
    /** Kept and sent together once a day. */
    case Digest = 'digest';
    /** Not received at all. */
    case None = 'none';

    /**
     * The switch of a category: on means at once, off means not at all.
     */
    public static function fromSwitch(bool $enabled): self
    {
        return $enabled ? self::Immediate : self::None;
    }
}
