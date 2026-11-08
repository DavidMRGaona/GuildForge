<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Feed;

use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Calendar\Services\ICalendarWriterInterface;
use App\Application\Calendar\Services\PublicCalendarFeedDocumentInterface;
use App\Application\Calendar\Services\PublicCalendarFeedInterface;
use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The public feed's document: every entry of the public calendar from 30 days back
 * to 12 months ahead, the same range as the personal feed. It is the same for
 * everyone, so it is written once every few minutes and kept in the cache; an
 * unreachable cache only costs writing it again.
 */
final readonly class PublicCalendarFeedDocument implements PublicCalendarFeedDocumentInterface
{
    public const int CACHE_SECONDS = 300;

    private const string PAST_WINDOW = '-30 days';

    private const string FUTURE_WINDOW = '+12 months';

    private const string CACHE_KEY = 'calendar:public-feed:';

    public function __construct(
        private CalendarAggregatorServiceInterface $calendar,
        private ICalendarWriterInterface $writer,
        private PublicCalendarFeedInterface $feed,
        private TimezoneServiceInterface $timezone,
        private Repository $cache,
    ) {}

    public function contents(): string
    {
        // Entry labels and the calendar name are translated
        $key = self::CACHE_KEY.app()->getLocale();

        try {
            return $this->cache->remember($key, self::CACHE_SECONDS, $this->write(...));
        } catch (Throwable $exception) {
            Log::warning('[PublicCalendarFeedDocument] Calendar feed cache unavailable', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $this->write();
    }

    private function write(): string
    {
        $now = $this->timezone->toStorage(CarbonImmutable::now());
        $entries = $this->calendar->findByDateRange(
            $now->modify(self::PAST_WINDOW),
            $now->modify(self::FUTURE_WINDOW),
        );

        return $this->writer->write($entries, $this->feed->calendarName());
    }
}
