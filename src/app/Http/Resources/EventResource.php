<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Services\TimezoneServiceInterface;
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
        $timezone = app(TimezoneServiceInterface::class);

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'startDate' => $timezone->iso($this->resource->startDate),
            'endDate' => $timezone->iso($this->resource->endDate),
            'location' => $this->resource->location,
            'memberPrice' => $this->resource->memberPrice,
            'nonMemberPrice' => $this->resource->nonMemberPrice,
            'imagePublicId' => $this->resource->imagePublicId,
            'isPublished' => $this->resource->isPublished,
            'createdAt' => $timezone->iso($this->resource->createdAt),
            'updatedAt' => $timezone->iso($this->resource->updatedAt),
            'downloadLinks' => $this->resource->downloadLinks,
            'tags' => TagResource::collection($this->resource->tags)->resolve(),
        ];
    }
}
