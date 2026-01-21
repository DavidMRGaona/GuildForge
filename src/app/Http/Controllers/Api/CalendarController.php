<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EventQueryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarEventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CalendarController extends Controller
{
    public function __construct(
        private readonly EventQueryServiceInterface $eventQueryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
        ]);

        $events = $this->eventQueryService->findByDateRange(
            $validated['start'],
            $validated['end']
        );

        return response()->json(
            CalendarEventResource::collection($events)->resolve()
        );
    }
}
