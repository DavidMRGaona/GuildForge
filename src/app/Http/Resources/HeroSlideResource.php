<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\HeroSlideResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property HeroSlideResponseDTO $resource
 */
final class HeroSlideResource extends JsonResource
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
            'subtitle' => $this->resource->subtitle,
            'buttonText' => $this->resource->buttonText,
            'buttonUrl' => $this->resource->buttonUrl,
            'imagePublicId' => $this->resource->imagePublicId,
            'isActive' => $this->resource->isActive,
            'sortOrder' => $this->resource->sortOrder,
        ];
    }
}
