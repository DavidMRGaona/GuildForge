<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

/**
 * Why a file was kept when asked to delete it. Each check runs again at delete time, so any of them can
 * refuse a file the library listed as unused.
 */
enum MediaDeletionRefusal: string
{
    /** The file is not in the media library index */
    case NotIndexed = 'not_indexed';

    /** The file sits in a folder of a hidden source (avatars) */
    case Hidden = 'hidden';

    /** No active source claims the file's folder */
    case Unclaimed = 'unclaimed';

    /** A record uses the file */
    case InUse = 'in_use';

    /** A source that is no longer active (a disabled module) had a use of the file */
    case UsedByInactiveSource = 'used_by_inactive_source';

    /** The file was uploaded within the grace period */
    case Recent = 'recent';

    /** One of the checks could not be completed */
    case CheckFailed = 'check_failed';

    /** The remote store did not confirm the deletion; nothing was written */
    case CatalogFailed = 'catalog_failed';
}
