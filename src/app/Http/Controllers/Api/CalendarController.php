<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CalendarRequest;
use App\Http\Resources\CalendarEntryResource;
use Illuminate\Http\JsonResponse;

final class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarAggregatorServiceInterface $calendarAggregatorService,
    ) {}

    public function index(CalendarRequest $request): JsonResponse
    {
        $entries = $this->calendarAggregatorService->findByDateRange(
            $request->startDateTime(),
            $request->endDateTime(),
        );

        return response()->json(
            CalendarEntryResource::collection($entries)->resolve()
        );
    }
}
