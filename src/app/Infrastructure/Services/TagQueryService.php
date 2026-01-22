<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\TagHierarchyDTO;
use App\Application\DTOs\Response\TagResponseDTO;
use App\Application\Services\TagQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Support\Collection;

final readonly class TagQueryService implements TagQueryServiceInterface
{
    /**
     * @return array<TagHierarchyDTO>
     */
    public function getAllInHierarchicalOrder(?string $type = null): array
    {
        $result = [];
        $query = TagModel::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($type !== null) {
            $query->whereJsonContains('applies_to', $type);
        }

        $roots = $query->get();

        foreach ($roots as $root) {
            $this->addWithDescendants($root, $result, 0, $root->name, $type);
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getOptionsForSelect(?string $type = null): array
    {
        $tags = $this->getAllInHierarchicalOrder($type);
        $options = [];

        foreach ($tags as $tag) {
            $options[$tag->id] = $tag->indentedNameForSelect;
        }

        return $options;
    }

    public function getUsageCount(string $tagId): int
    {
        $tag = TagModel::query()->find($tagId);

        if ($tag === null) {
            return 0;
        }

        return $tag->events()->count()
            + $tag->articles()->count()
            + $tag->galleries()->count();
    }

    public function hasChildren(string $tagId): bool
    {
        return TagModel::query()
            ->where('parent_id', $tagId)
            ->exists();
    }

    public function isInUse(string $tagId): bool
    {
        return $this->getUsageCount($tagId) > 0;
    }

    public function canDelete(string $tagId): bool
    {
        return !$this->hasChildren($tagId) && !$this->isInUse($tagId);
    }

    /**
     * Recursively add a tag and its descendants to the result array.
     *
     * @param array<TagHierarchyDTO> $result
     */
    private function addWithDescendants(
        TagModel $tag,
        array &$result,
        int $depth,
        string $pathSoFar,
        ?string $type = null,
    ): void {
        $tagResponseDTO = new TagResponseDTO(
            id: $tag->id,
            name: $tag->name,
            slug: $tag->slug,
            parentId: $tag->parent_id,
            parentName: $tag->parent?->name,
            appliesTo: $tag->applies_to,
            color: $tag->color,
            sortOrder: $tag->sort_order,
        );

        $result[] = TagHierarchyDTO::fromTagResponse(
            tag: $tagResponseDTO,
            depth: $depth,
            fullPath: $pathSoFar,
            description: $tag->description,
        );

        $childQuery = TagModel::query()
            ->where('parent_id', $tag->id)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($type !== null) {
            $childQuery->whereJsonContains('applies_to', $type);
        }

        $children = $childQuery->get();

        foreach ($children as $child) {
            $childPath = $pathSoFar . ' > ' . $child->name;
            $this->addWithDescendants($child, $result, $depth + 1, $childPath, $type);
        }
    }
}
