<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Repositories\EventRepositoryInterface;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CalendarController extends Controller
{
    private const AMBER_500 = '#f59e0b';

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
        ]);

        $start = new DateTimeImmutable($validated['start']);
        $end = new DateTimeImmutable($validated['end']);

        $events = $this->eventRepository->findByDateRange($start, $end);

        $calendarEvents = $events->map(function ($event) {
            return [
                'id' => $event->id()->value,
                'title' => $event->title(),
                'slug' => $event->slug()->value,
                'description' => $event->description(),
                'start' => $event->startDate()->format('c'),
                'end' => $event->endDate()?->format('c'),
                'location' => $event->location(),
                'imagePublicId' => $event->imagePublicId(),
                'memberPrice' => $event->memberPrice()?->value,
                'nonMemberPrice' => $event->nonMemberPrice()?->value,
                'url' => '/eventos/' . $event->slug()->value,
                'backgroundColor' => self::AMBER_500,
            ];
        })->values()->toArray();

        return response()->json($calendarEvents);
    }
}
