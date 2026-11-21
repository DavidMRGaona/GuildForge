<?php

declare(strict_types=1);

namespace App\Domain\Profiles;

/**
 * When a public profile counts as complete (core#777): the account has a public name, an avatar and
 * a «Sobre mí», none of them blank. It is the one place that says so; the activity source repeats
 * it as a query and its tests check both agree.
 */
final class ProfileCompletenessRule
{
    public static function isComplete(?string $publicName, ?string $avatarPublicId, ?string $bio): bool
    {
        return self::filled($publicName) && self::filled($avatarPublicId) && self::filled($bio);
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
