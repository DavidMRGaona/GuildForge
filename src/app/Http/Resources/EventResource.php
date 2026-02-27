<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\EventResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property EventResponseDTO $resource
 */
final class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'startDate' => $this->resource->startDate->format('c'),
            'endDate' => $this->resource->endDate->format('c'),
            'location' => $this->resource->location,
            'memberPrice' => $this->resource->memberPrice,
            'nonMemberPrice' => $this->resource->nonMemberPrice,
            'imagePublicId' => $this->resource->imagePublicId,
            'isPublished' => $this->resource->isPublished,
            'createdAt' => $this->resource->createdAt?->format('c'),
            'updatedAt' => $this->resource->updatedAt?->format('c'),
            'downloadLinks' => $this->resource->downloadLinks,
            'tags' => TagResource::collection($this->resource->tags)->resolve(),
        ];
    }
}
