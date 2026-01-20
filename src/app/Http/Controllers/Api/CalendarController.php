<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarEventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
        ]);

        $events = EventModel::query()
            ->with('tags')
            ->where('is_published', true)
            ->where(function ($query) use ($validated): void {
                // Event starts within range
                $query->whereBetween('start_date', [$validated['start'], $validated['end']])
                    // Event ends within range (for events starting before range)
                    ->orWhere(function ($q) use ($validated): void {
                        $q->whereNotNull('end_date')
                            ->whereBetween('end_date', [$validated['start'], $validated['end']]);
                    })
                    // Event spans the entire range (must have end_date)
                    ->orWhere(function ($q) use ($validated): void {
                        $q->where('start_date', '<', $validated['start'])
                            ->whereNotNull('end_date')
                            ->where('end_date', '>', $validated['end']);
                    });
            })
            ->orderBy('start_date')
            ->get();

        return response()->json(
            CalendarEventResource::collection($events)->resolve()
        );
    }
}
