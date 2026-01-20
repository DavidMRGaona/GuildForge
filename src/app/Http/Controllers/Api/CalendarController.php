<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Repositories\EventRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarEventResource;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CalendarController extends Controller
{
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

        return response()->json(
            CalendarEventResource::collection($events)->resolve()
        );
    }
}
