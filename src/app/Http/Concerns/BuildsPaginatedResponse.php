<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Application\DTOs\Response\PaginatedResponseDTO;
use Illuminate\Http\Resources\Json\JsonResource;

trait BuildsPaginatedResponse
{
    /**
     * Build a paginated response for Inertia.
     *
     * @param  array<int, mixed>  $items
     * @param  class-string<JsonResource>  $resourceClass
     * @return array{data: array<int, mixed>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}, links: array{first: string, last: string, prev: string|null, next: string|null}}
     */
    protected function buildPaginatedResponse(
        array $items,
        int $total,
        int $page,
        int $perPage,
        string $resourceClass,
    ): array {
        $paginated = PaginatedResponseDTO::create(
            data: $resourceClass::collection($items)->resolve(),
            currentPage: $page,
            total: $total,
            perPage: $perPage,
            baseUrl: url()->current(),
        );

        return $paginated->toArray();
    }
}
