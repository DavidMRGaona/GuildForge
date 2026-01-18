<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Repositories\EventRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EventController extends Controller
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    ) {
    }

    public function index(): Response
    {
        $events = EventModel::query()
            ->where('is_published', true)
            ->orderBy('start_date', 'desc')
            ->paginate(12);

        return Inertia::render('Events/Index', [
            'events' => [
                'data' => $events->map(fn (EventModel $event): array => $this->mapEventModel($event)),
                'meta' => [
                    'currentPage' => $events->currentPage(),
                    'lastPage' => $events->lastPage(),
                    'perPage' => $events->perPage(),
                    'total' => $events->total(),
                ],
                'links' => [
                    'first' => $events->url(1),
                    'last' => $events->url($events->lastPage()),
                    'prev' => $events->previousPageUrl(),
                    'next' => $events->nextPageUrl(),
                ],
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $event = $this->eventRepository->findBySlug($slug);

        if ($event === null || !$event->isPublished()) {
            abort(404);
        }

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id()->value,
                'title' => $event->title(),
                'slug' => $event->slug()->value,
                'description' => $event->description(),
                'startDate' => $event->startDate()->format('c'),
                'endDate' => $event->endDate()?->format('c'),
                'location' => $event->location(),
                'memberPrice' => $event->memberPrice()?->value,
                'nonMemberPrice' => $event->nonMemberPrice()?->value,
                'imagePublicId' => $event->imagePublicId(),
                'isPublished' => $event->isPublished(),
                'createdAt' => $event->createdAt()?->format('c'),
                'updatedAt' => $event->updatedAt()?->format('c'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEventModel(EventModel $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'startDate' => $event->start_date->format('c'),
            'endDate' => $event->end_date?->format('c'),
            'location' => $event->location,
            'memberPrice' => $event->member_price !== null ? floatval($event->member_price) : null,
            'nonMemberPrice' => $event->non_member_price !== null ? floatval($event->non_member_price) : null,
            'imagePublicId' => $event->image_public_id,
            'isPublished' => $event->is_published,
            'createdAt' => $event->created_at?->format('c'),
            'updatedAt' => $event->updated_at?->format('c'),
        ];
    }
}
