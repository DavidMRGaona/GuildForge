<?php

declare(strict_types=1);

namespace App\Application\SiteNotifications\Contracts;

use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;

/**
 * A Laravel notification that can also appear in the site's notification centre (the bell).
 *
 * It goes through the core's channel (`SiteNotificationChannel::NAME`, «site», in via()) and must
 * also implement HasMailCategory with a category registered with `site: true`: the person turns it
 * on and off by that category, and HasMailSubject mutes it together with the mail about that thing.
 */
interface HasSiteNotification
{
    /** Name of the core's channel, to return from via(): `['mail', HasSiteNotification::CHANNEL]`. */
    public const string CHANNEL = 'site';

    /**
     * The notice for this person, built when it is delivered (inside the queue worker for a
     * queued notification). Return null when they should not get it any more, for instance when
     * they can no longer see what it is about: nothing is stored then.
     */
    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO;
}
