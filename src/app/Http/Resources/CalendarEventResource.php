<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\EventResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property EventResponseDTO $resource
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
        $tags = array_map(fn ($tag): array => [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
            'parentId' => $tag->parentId,
        ], $this->resource->tags);

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'start' => $this->resource->startDate->format('c'),
            'end' => $this->resource->endDate->format('c'),
            'location' => $this->resource->location,
            'imagePublicId' => $this->resource->imagePublicId,
            'memberPrice' => $this->resource->memberPrice,
            'nonMemberPrice' => $this->resource->nonMemberPrice,
            'url' => '/eventos/'.$this->resource->slug,
            'backgroundColor' => self::AMBER_500,
            'tags' => $tags,
        ];
    }
}
