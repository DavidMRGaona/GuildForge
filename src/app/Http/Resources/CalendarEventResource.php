<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Entities\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Event $resource
 */
final class CalendarEventResource extends JsonResource
{
    private const AMBER_500 = '#f59e0b';

    /**
     * Transform the resource into an array for calendar display.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id()->value,
            'title' => $this->resource->title(),
            'slug' => $this->resource->slug()->value,
            'description' => $this->resource->description(),
            'start' => $this->resource->startDate()->format('c'),
            'end' => $this->resource->endDate()?->format('c'),
            'location' => $this->resource->location(),
            'imagePublicId' => $this->resource->imagePublicId(),
            'memberPrice' => $this->resource->memberPrice()?->value,
            'nonMemberPrice' => $this->resource->nonMemberPrice()?->value,
            'url' => '/eventos/' . $this->resource->slug()->value,
            'backgroundColor' => self::AMBER_500,
        ];
    }
}
