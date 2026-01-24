<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Resources\EventResource;
use App\Http\Resources\TagResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EventController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly TagQueryServiceInterface $tagQuery,
    ) {}

    public function index(Request $request): Response
    {
        $page = $this->getCurrentPage();
        $tagsParam = $request->query('tags');
        $tagSlugs = null;
        if (is_string($tagsParam) && $tagsParam !== '') {
            $tagSlugs = array_filter(explode(',', $tagsParam));
        }

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
        ]);
    }

    public function show(string $slug): Response
    {
        $event = $this->eventQuery->findPublishedBySlug($slug);

        if ($event === null) {
            abort(404);
        }

        return Inertia::render('Events/Show', [
            'event' => EventResource::make($event)->resolve(),
        ]);
    }
}
