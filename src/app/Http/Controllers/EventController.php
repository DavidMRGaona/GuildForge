<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\AddToCalendarLinksFactoryInterface;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Discussions\Contracts\SubjectDiscussionInterface;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Requests\TagFilterRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\TagResource;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class EventController extends Controller
{
    use BuildsPaginatedResponse;

    private const int PER_PAGE = 12;

    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly TagQueryServiceInterface $tagQuery,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly CalendarAggregatorServiceInterface $calendar,
        private readonly AddToCalendarLinksFactoryInterface $calendarLinks,
        private readonly SubjectDiscussionInterface $discussions,
        private readonly TimezoneServiceInterface $timezone,
    ) {}

    public function index(TagFilterRequest $request): Response
    {
        $page = $request->getPage();
        $tagSlugs = $request->getTagSlugs();

        $events = $this->eventQuery->getPublishedEventsPaginated($page, self::PER_PAGE, $tagSlugs);
        $total = $this->eventQuery->getPublishedEventsTotal($tagSlugs);

        $availableTags = $this->tagQuery->getByType('events');

        return Inertia::render('Events/Index', [
            'events' => $this->buildPaginatedResponse(
                items: $events,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: EventResource::class,
            ),
            'tags' => TagResource::collection($availableTags)->resolve(),
            'currentTags' => $tagSlugs ?? [],
            'seo' => $this->seo->forPage(new SeoPageDTO(title: (string) __('seo.titles.events')))->toArray(),
        ]);
    }

    public function show(string $slug): Response
    {
        $event = $this->eventQuery->findPublishedBySlug($slug);

        if (! $event instanceof EventResponseDTO) {
            abort(404);
        }

        $calendarEntry = $this->calendar->findEntry('event', $event->id);

        return Inertia::render('Events/Show', [
            'event' => EventResource::make($event)->resolve(),
            'seo' => $this->seo->forEvent($event)->toArray(),
            'calendarLinks' => $calendarEntry instanceof CalendarEntryDTO
                ? $this->calendarLinks->for($calendarEntry)->toArray()
                : null,
            'discussion' => $this->discussion($event->id),
        ]);
    }

    /**
     * Its discussion in the forum, when a forum module offers one. Whatever goes wrong there, the
     * page shows without the block.
     *
     * @return array<string, mixed>|null
     */
    private function discussion(string $eventId): ?array
    {
        try {
            $userId = Auth::id();

            return $this->discussions->forSubject('event', $eventId, $userId !== null ? (string) $userId : null)?->toArray($this->timezone);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
