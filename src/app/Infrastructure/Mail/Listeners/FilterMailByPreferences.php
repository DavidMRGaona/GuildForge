<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Listeners;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cancels a mail notification to an account that does not want that kind of mail.
 *
 * Laravel stops a notification when a NotificationSending listener returns false, and stops
 * asking further listeners at the first answer that is not null: so this returns null, never
 * true, whenever the mail may go out. Bound scoped, so each request or queued job warns once
 * per notification class without a known category.
 */
final class FilterMailByPreferences
{
    /**
     * @var array<class-string, true>
     */
    private array $warned = [];

    public function __construct(
        private readonly MailCategoryRegistryInterface $categories,
        private readonly MailPreferenceServiceInterface $preferences,
    ) {}

    public function handle(NotificationSending $event): ?false
    {
        // Guests and plain addresses (Notification::route) have no preferences
        if (! in_array($event->channel, ['mail', MailChannel::class], true) || ! $event->notifiable instanceof UserModel) {
            return null;
        }

        // An anonymized account is nobody any more: its address is a placeholder
        if ($event->notifiable->anonymized_at !== null) {
            return false;
        }

        try {
            $category = $this->categories->forNotification($event->notification);

            if (! $category instanceof MailCategoryDTO) {
                $this->warnOnce($event->notification);

                return null;
            }

            return $this->preferences->receives($event->notifiable, $category) ? null : false;
        } catch (Throwable $exception) {
            Log::warning('Could not check the mail preferences; the mail is sent.', [
                'notification' => $event->notification::class,
                'user_id' => $event->notifiable->id,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    private function warnOnce(object $notification): void
    {
        if (isset($this->warned[$notification::class])) {
            return;
        }

        $this->warned[$notification::class] = true;

        Log::warning('A mail notification without a registered mail category was sent; it cannot be turned off.', [
            'notification' => $notification::class,
            'category' => $notification instanceof HasMailCategory ? $notification->mailCategory() : null,
        ]);
    }
}
