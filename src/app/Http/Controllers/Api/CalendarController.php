<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\EventQueryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CalendarRequest;
use App\Http\Resources\CalendarEventResource;
use Illuminate\Http\JsonResponse;

final class CalendarController extends Controller
{
    public function __construct(
        private readonly EventQueryServiceInterface $eventQueryService,
    ) {
    }

    public function index(CalendarRequest $request): JsonResponse
    {
        $events = $this->eventQueryService->findByDateRange(
            $request->startDate(),
            $request->endDate()
        );

        return response()->json(
            CalendarEventResource::collection($events)->resolve()
        );
    }
}
