<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\DTOs\Response\ArticleResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ArticleResponseDTO $resource
 */
final class ArticleResource extends JsonResource
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
            'content' => $this->resource->content,
            'excerpt' => $this->resource->excerpt,
            'featuredImagePublicId' => $this->resource->featuredImage,
            'isPublished' => $this->resource->isPublished,
            'publishedAt' => $this->resource->publishedAt?->format('c'),
            'author' => $this->resource->author !== null
                ? (new AuthorResource($this->resource->author))->toArray($request)
                : null,
            'createdAt' => $this->resource->createdAt?->format('c'),
            'updatedAt' => $this->resource->updatedAt?->format('c'),
            'tags' => TagResource::collection($this->resource->tags)->resolve(),
        ];
    }
}
