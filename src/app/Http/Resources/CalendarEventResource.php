<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property EventModel $resource
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
        $tags = $this->resource->tags->map(fn ($tag): array => [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
            'parentId' => $tag->parent_id,
        ])->all();

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'start' => $this->resource->start_date->format('c'),
            'end' => $this->resource->end_date?->format('c'),
            'location' => $this->resource->location,
            'imagePublicId' => $this->resource->image_public_id,
            'memberPrice' => $this->resource->member_price !== null ? (float) $this->resource->member_price : null,
            'nonMemberPrice' => $this->resource->non_member_price !== null ? (float) $this->resource->non_member_price : null,
            'url' => '/eventos/' . $this->resource->slug,
            'backgroundColor' => self::AMBER_500,
            'tags' => $tags,
        ];
    }
}
