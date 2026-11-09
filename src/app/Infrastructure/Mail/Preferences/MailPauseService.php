<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Mail\Enums\MailPauseDuration;
use App\Domain\Mail\Repositories\MailPauseRepositoryInterface;
use App\Domain\Mail\ValueObjects\MailPause;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MailPauseService implements MailPauseServiceInterface
{
    private const int WEEK_IN_DAYS = 7;

    public function __construct(
        private MailPauseRepositoryInterface $pauses,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function current(object $user): ?MailPause
    {
        if (! $user instanceof UserModel) {
            return null;
        }

        $pause = $this->pauses->find(UserId::fromString($user->id));

        return $pause instanceof MailPause && $pause->isActiveAt(now()) ? $pause : null;
    }

    public function pause(object $user, MailPauseDuration $duration): MailPause
    {
        if (! $user instanceof UserModel) {
            throw new InvalidArgumentException('Only accounts can pause their mail.');
        }

        return $this->pauses->pause(UserId::fromString($user->id), $this->endFor($duration));
    }

    public function resume(object $user): void
    {
        if ($user instanceof UserModel) {
            $this->pauses->resume(UserId::fromString($user->id));
        }
    }

    /**
     * The pause lasts through the display day that is a week or a month away, so «until 12
     * December» includes the 12th: it ends where the next day starts.
     */
    private function endFor(MailPauseDuration $duration): ?DateTimeImmutable
    {
        $today = CarbonImmutable::instance($this->timezone->today());

        $lastDay = match ($duration) {
            MailPauseDuration::Indefinite => null,
            MailPauseDuration::Week => $today->addDays(self::WEEK_IN_DAYS),
            MailPauseDuration::Month => $today->addMonthNoOverflow(),
        };

        return $lastDay === null ? null : $this->timezone->displayDayRange($lastDay)[1];
    }
}
