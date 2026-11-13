<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNews;

use App\Domain\SiteNews\SiteNewsKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Which content was already announced (`site_news_announcements`). Written in the same
 * transaction as the publication, so a rolled back publication leaves no mark.
 */
final class SiteNewsAnnouncements
{
    private const string TABLE = 'site_news_announcements';

    /**
     * Marks the content as announced; false when it already was. Two saves at once cannot both
     * claim it: the unique key lets only one row in.
     */
    public function claim(SiteNewsKind $kind, string $id): bool
    {
        return DB::table(self::TABLE)->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'kind' => $kind->value,
            'subject_id' => $id,
            'announced_at' => now(),
        ]) === 1;
    }

    /**
     * Drops the mark of deleted content; false when it was never announced.
     */
    public function forget(SiteNewsKind $kind, string $id): bool
    {
        return DB::table(self::TABLE)->where('kind', $kind->value)->where('subject_id', $id)->delete() > 0;
    }
}
