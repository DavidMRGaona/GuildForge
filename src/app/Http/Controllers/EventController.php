<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\EventQueryServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Resources\EventResource;
use Inertia\Inertia;
use Inertia\Response;

final class EventController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
    ) {
    }

    public function index(): Response
    {
        $page = $this->getCurrentPage();

        $events = $this->eventQuery->getPublishedEventsPaginated($page, self::PER_PAGE);
        $total = $this->eventQuery->getPublishedEventsTotal();

        return Inertia::render('Events/Index', [
            'events' => $this->buildPaginatedResponse(
                items: $events,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: EventResource::class,
            ),
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
