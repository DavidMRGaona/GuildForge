<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property GalleryDetailResponseDTO $resource
 */
final class GalleryWithPhotosResource extends JsonResource
{
    /**
     * Transform the resource into an array with photos included.
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
            'isPublished' => $this->resource->isPublished,
            'isFeatured' => $this->resource->isFeatured,
            'photoCount' => count($this->resource->photos),
            'createdAt' => $timezone->iso($this->resource->createdAt),
            'updatedAt' => $timezone->iso($this->resource->updatedAt),
            'photos' => PhotoResource::collection($this->resource->photos)->resolve(),
            'tags' => TagResource::collection($this->resource->tags)->resolve(),
        ];
    }
}
