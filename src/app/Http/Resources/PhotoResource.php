<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\PhotoResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PhotoResponseDTO $resource
 */
final class PhotoResource extends JsonResource
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
            'imagePublicId' => $this->resource->imagePublicId,
            'caption' => $this->resource->caption,
            'sortOrder' => $this->resource->sortOrder,
        ];
    }
}
