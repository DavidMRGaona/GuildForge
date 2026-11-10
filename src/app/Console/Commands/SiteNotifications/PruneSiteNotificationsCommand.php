<?php

declare(strict_types=1);

namespace App\Console\Commands\SiteNotifications;

use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use Illuminate\Console\Command;

/**
 * Keeps the notification centre short: read notices go after 90 days and unread ones after 180.
 */
final class PruneSiteNotificationsCommand extends Command
{
    public const int READ_DAYS = 90;

    public const int UNREAD_DAYS = 180;

    protected $signature = 'site-notifications:prune';

    protected $description = 'Delete notification centre notices read more than 90 days ago or unread for more than 180';

    public function handle(SiteNotificationServiceInterface $notices): int
    {
        $this->info('Pruned: '.$notices->prune(self::READ_DAYS, self::UNREAD_DAYS));

        return self::SUCCESS;
    }
}
