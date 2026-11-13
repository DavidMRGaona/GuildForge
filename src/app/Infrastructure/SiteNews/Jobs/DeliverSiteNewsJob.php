<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNews\Jobs;

use App\Domain\SiteNews\SiteNewsKind;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\SiteNotifications\SiteNotificationChannel;
use App\Notifications\SiteNewsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers the news of one publication to one batch of accounts. One notification for the whole
 * batch, so the content is read once.
 *
 * It goes straight to the site channel, which decides for each account (active, wants it, not
 * muted): the news is never mailed, so nothing else would run through the notification facade,
 * and a test faking notifications for its own module does not see everybody's news.
 */
final class DeliverSiteNewsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** A retry would add the notice again to the accounts already reached */
    public int $tries = 1;

    /**
     * @param  list<string>  $accountIds
     */
    public function __construct(
        public readonly SiteNewsKind $kind,
        public readonly string $id,
        public readonly array $accountIds,
    ) {}

    public function handle(SiteNotificationChannel $channel): void
    {
        $notification = new SiteNewsNotification($this->kind, $this->id);

        foreach (UserModel::query()->whereKey($this->accountIds)->get() as $account) {
            try {
                $channel->send($account, $notification);
            } catch (Throwable $exception) {
                // One account that cannot be reached does not leave the rest of the batch without it
                Log::warning('A site news notice could not be delivered.', ['kind' => $this->kind->value, 'id' => $this->id, 'user_id' => $account->id, 'exception' => $exception->getMessage()]);
            }
        }
    }
}
