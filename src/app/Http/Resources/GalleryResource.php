<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\GalleryResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property GalleryResponseDTO $resource
 */
final class GalleryResource extends JsonResource
{
    /**
     * Transform the resource into an array (without photos).
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
            'isPublished' => $this->resource->isPublished,
            'isFeatured' => $this->resource->isFeatured,
            'photoCount' => $this->resource->photoCount,
            'createdAt' => $this->resource->createdAt?->format('c'),
            'updatedAt' => $this->resource->updatedAt?->format('c'),
            'tags' => TagResource::collection($this->resource->tags)->resolve(),
        ];
    }
}
