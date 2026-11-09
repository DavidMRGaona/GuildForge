<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Digest;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailDigestServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Mail\Repositories\MailDigestItemRepositoryInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Mail\Digest\Jobs\SendMailDigestJob;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The digest goes out once a day at the hour set in the panel (display zone). The scheduler
 * asks every hour: each run sends what was kept before the last time it was that hour, so a run
 * missed while the server was down is caught up an hour later, and a second run finds nothing
 * left (what was sent is deleted).
 */
final readonly class MailDigestService implements MailDigestServiceInterface
{
    public const string HOUR_SETTING = 'mail_digest_hour';

    public const int DEFAULT_HOUR = 8;

    /** A notice that could not be sent for this long is dropped. */
    public const int KEEP_DAYS = 7;

    public function __construct(
        private MailPreferenceRepositoryInterface $preferences,
        private MailDigestItemRepositoryInterface $items,
        private SettingsServiceInterface $settings,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function holds(object $user, MailCategoryDTO $category, object $notification, ?MailSubjectDTO $subject): bool
    {
        if (! $user instanceof UserModel || $category->isMandatory() || ! $category->digestible) {
            return false;
        }

        $userId = UserId::fromString($user->id);

        if (! in_array($category->key, $this->preferences->digestFor($userId), true)) {
            return false;
        }

        $line = NotificationDigestLine::of($notification, $user);

        if (! $line instanceof MailDigestLineDTO) {
            return false;
        }

        $this->items->add($userId, $category->key, $line->text, $line->url, $subject?->type, $subject?->id);

        return true;
    }

    public function dispatchDue(): int
    {
        $now = CarbonImmutable::now();

        $pruned = $this->items->pruneBefore($now->subDays(self::KEEP_DAYS));
        if ($pruned > 0) {
            Log::warning('Notices kept for the daily digest could not be sent for a week and were dropped.', [
                'count' => $pruned,
            ]);
        }

        $cutoff = $this->latestCutoff($now);
        $userIds = $this->items->userIdsWithItemsBefore($cutoff);

        foreach ($userIds as $userId) {
            try {
                SendMailDigestJob::dispatch($userId, $cutoff->format(DateTimeInterface::ATOM));
            } catch (Throwable $exception) {
                // On a sync queue the job runs here: one failed digest never stops the others,
                // and its notices wait for the next run
                Log::warning('A daily mail digest could not be sent; it is tried again in the next run.', [
                    'user_id' => $userId,
                    'exception' => $exception,
                ]);
            }
        }

        return count($userIds);
    }

    public function time(): string
    {
        return sprintf('%02d:00', $this->hour());
    }

    /**
     * The last instant, up to now, at which the display zone read the digest hour. Built from the
     * wall time of the display day, so it follows summer time.
     */
    public function latestCutoff(DateTimeInterface $now): DateTimeImmutable
    {
        $local = CarbonImmutable::instance($this->timezone->toDisplay($now));
        $today = $local->setTime($this->hour(), 0);

        return $this->timezone->toStorage($today->greaterThan($local) ? $today->subDay()->setTime($this->hour(), 0) : $today);
    }

    private function hour(): int
    {
        $value = $this->settings->get(self::HOUR_SETTING, (string) self::DEFAULT_HOUR);
        $hour = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 23]]);

        return is_int($hour) ? $hour : self::DEFAULT_HOUR;
    }
}
