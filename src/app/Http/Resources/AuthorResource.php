<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\AuthorResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AuthorResponseDTO|null $resource
 */
final class AuthorResource extends JsonResource
{
    /**
     * Transform the resource into an array (author information only).
     *
     * @return array<string, mixed>|null
     */
    public function toArray(Request $request): ?array
    {
        if ($this->resource === null) {
            return null;
        }

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'displayName' => $this->resource->displayName,
            'avatarPublicId' => $this->resource->avatarPublicId,
        ];
    }
}
