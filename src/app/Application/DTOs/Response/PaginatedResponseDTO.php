<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class PaginatedResponseDTO
{
    /**
     * @param array<int, mixed> $data
     * @param array{currentPage: int, lastPage: int, perPage: int, total: int} $meta
     * @param array{first: string, last: string, prev: string|null, next: string|null} $links
     */
    public function __construct(
        public array $data,
        public array $meta,
        public array $links,
    ) {
    }

    /**
     * @param array<int, mixed> $data
     */
    public static function create(
        array $data,
        int $currentPage,
        int $total,
        int $perPage,
        string $baseUrl,
    ): self {
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return new self(
            data: $data,
            meta: [
                'currentPage' => $currentPage,
                'lastPage' => $lastPage,
                'perPage' => $perPage,
                'total' => $total,
            ],
            links: [
                'first' => $baseUrl . '?page=1',
                'last' => $baseUrl . '?page=' . $lastPage,
                'prev' => $currentPage > 1 ? $baseUrl . '?page=' . ($currentPage - 1) : null,
                'next' => $currentPage < $lastPage ? $baseUrl . '?page=' . ($currentPage + 1) : null,
            ],
        );
    }

    /**
     * Convert to array format suitable for Inertia responses.
     *
     * @return array{data: array<int, mixed>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}, links: array{first: string, last: string, prev: string|null, next: string|null}}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
            'links' => $this->links,
        ];
    }
}
