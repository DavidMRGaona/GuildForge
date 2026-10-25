<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CalendarEntryDTO $resource
 */
final class CalendarEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array for calendar display.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(TimezoneServiceInterface::class);

        return array_merge([
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'start' => $timezone->iso($this->resource->start),
            'end' => $timezone->iso($this->resource->end),
            'url' => $this->resource->url,
            'sourceType' => $this->resource->sourceType,
            'sourceLabel' => $this->resource->sourceLabel,
            'color' => $this->resource->color->value,
        ], $this->resource->details);
    }
}
