<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\AddToCalendarLinksFactoryInterface;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Application\Proposals\Services\ProposalQueryServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Requests\TagFilterRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\TagResource;
use Inertia\Inertia;
use Inertia\Response;

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
        private readonly ProposalEligibilityServiceInterface $proposalAccess,
        private readonly ProposalQueryServiceInterface $proposals,
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
            // Only for whoever may propose one (core#579): the button never shows otherwise
            'canPropose' => $this->proposalAccess->check(ProposalType::Event, $request->user()?->getAuthIdentifier() === null ? null : (string) $request->user()->getAuthIdentifier())->allowed,
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
            // «Propuesto por…», when the setting is on and the event was proposed (core#579)
            'proposedBy' => $this->proposals->proposerNameOfEvent($event->id),
            'seo' => $this->seo->forEvent($event)->toArray(),
            'calendarLinks' => $calendarEntry instanceof CalendarEntryDTO
                ? $this->calendarLinks->for($calendarEntry)->toArray()
                : null,
        ]);
    }
}
