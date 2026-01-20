<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\TagResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TagResponseDTO $resource
 */
final class TagResource extends JsonResource
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
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'parentId' => $this->resource->parentId,
            'parentName' => $this->resource->parentName,
            'appliesTo' => $this->resource->appliesTo,
            'color' => $this->resource->color,
            'sortOrder' => $this->resource->sortOrder,
        ];
    }
}
