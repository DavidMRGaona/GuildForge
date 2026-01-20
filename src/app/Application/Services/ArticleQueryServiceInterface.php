<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\ArticleResponseDTO;

interface ArticleQueryServiceInterface
{
    /**
     * @return array<int, ArticleResponseDTO>
     */
    public function getLatestPublished(int $limit = 10): array;

    /**
     * @return array<int, ArticleResponseDTO>
     */
    public function getPublishedPaginated(int $page = 1, int $perPage = 12): array;

    public function getPublishedTotal(): int;

    public function findPublishedBySlug(string $slug): ?ArticleResponseDTO;

    /**
     * @return array<int, ArticleResponseDTO>
     */
    public function searchPublished(string $query, int $limit = 12): array;
}
