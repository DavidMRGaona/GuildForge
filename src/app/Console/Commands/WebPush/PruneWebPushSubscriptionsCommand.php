<?php

declare(strict_types=1);

namespace App\Console\Commands\WebPush;

use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use Illuminate\Console\Command;

/**
 * Forgets the browsers nothing has used for 90 days (core#803): no message delivered and the
 * browser never came back to the site with it. Runs daily.
 */
final class PruneWebPushSubscriptionsCommand extends Command
{
    public const int UNUSED_DAYS = 90;

    protected $signature = 'web-push:prune';

    protected $description = 'Delete browser push subscriptions unused for 90 days';

    public function handle(WebPushSubscriptionServiceInterface $subscriptions): int
    {
        $deleted = $subscriptions->prune(now()->subDays(self::UNUSED_DAYS));

        $this->info("Deleted {$deleted} unused push subscriptions.");

        return self::SUCCESS;
    }
}
