<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\TagHierarchyDTO;
use App\Application\DTOs\Response\TagResponseDTO;

interface TagQueryServiceInterface
{
    /**
     * Get tags by type for filtering.
     *
     * @return array<TagResponseDTO>
     */
    public function getByType(string $type): array;

    /**
     * Get all tags in hierarchical order as a flat collection.
     *
     * @param  string|null  $type  Filter by type (events, articles, galleries)
     * @return array<TagHierarchyDTO>
     */
    public function getAllInHierarchicalOrder(?string $type = null): array;

    /**
     * Get tags formatted for select dropdowns.
     *
     * @param  string|null  $type  Filter by type (events, articles, galleries)
     * @return array<string, string> [id => indented name]
     */
    public function getOptionsForSelect(?string $type = null): array;

    /**
     * Get the total usage count for a tag across all content types.
     */
    public function getUsageCount(string $tagId): int;

    /**
     * Check if a tag has any children.
     */
    public function hasChildren(string $tagId): bool;

    /**
     * Check if a tag is in use by any content.
     */
    public function isInUse(string $tagId): bool;

    /**
     * Check if a tag can be safely deleted.
     */
    public function canDelete(string $tagId): bool;
}
