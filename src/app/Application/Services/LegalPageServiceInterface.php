<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\LegalPageResponseDTO;

interface LegalPageServiceInterface
{
    /**
     * Get a published legal page by its slug.
     *
     * @return LegalPageResponseDTO|null The page DTO if published, null otherwise
     */
    public function getPublishedPage(string $slug): ?LegalPageResponseDTO;

    /**
     * Check if a slug corresponds to a known legal page.
     */
    public function isValidSlug(string $slug): bool;

    /**
     * The published legal pages, in their usual order: slug => title.
     *
     * @return array<string, string>
     */
    public function getPublishedTitles(): array;
}
