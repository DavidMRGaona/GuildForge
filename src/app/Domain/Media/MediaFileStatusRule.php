<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Media\Enums\MediaFileStatus;
use DateTimeImmutable;

final class MediaFileStatusRule
{
    public const int GRACE_DAYS = 7;

    public static function for(
        bool $hasUsages,
        bool $claimedByActiveSource,
        DateTimeImmutable $uploadedAt,
        DateTimeImmutable $now,
    ): MediaFileStatus {
        if ($hasUsages) {
            return MediaFileStatus::InUse;
        }

        if (! $claimedByActiveSource) {
            return MediaFileStatus::UnknownOrigin;
        }

        $graceEnd = $uploadedAt->modify(sprintf('+%d days', self::GRACE_DAYS));

        return $graceEnd > $now ? MediaFileStatus::Recent : MediaFileStatus::Unused;
    }
}
