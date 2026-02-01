<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\TagHierarchyDTO;
use App\Application\DTOs\Response\TagResponseDTO;
use App\Application\Services\TagQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;

final readonly class TagQueryService implements TagQueryServiceInterface
{
    /**
     * @return array<TagHierarchyDTO>
     */
    public function getAllInHierarchicalOrder(?string $type = null): array
    {
        $result = [];

        // Load all tags in a single query with recursive eager loading
        $query = TagModel::query()
            ->whereNull('parent_id')
            ->with(['children' => $this->buildChildrenEagerLoader($type)])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($type !== null) {
            $query->whereJsonContains('applies_to', $type);
        }

        $roots = $query->get();

        foreach ($roots as $root) {
            $this->addWithDescendantsFromEager($root, $result, 0, $root->name, $type);
        }

        return $result;
    }

    /**
     * Build a recursive eager loader for children relationships.
     */
    private function buildChildrenEagerLoader(?string $type): \Closure
    {
        return function ($query) use ($type): void {
            $query->orderBy('sort_order')->orderBy('name');

            if ($type !== null) {
                $query->whereJsonContains('applies_to', $type);
            }

            // Recursively eager load children
            $query->with(['children' => $this->buildChildrenEagerLoader($type)]);
        };
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
        $tag = TagModel::query()
            ->withCount(['events', 'articles', 'galleries'])
            ->find($tagId);

        if ($tag === null) {
            return 0;
        }

        /** @var int $eventsCount */
        $eventsCount = $tag->events_count;
        /** @var int $articlesCount */
        $articlesCount = $tag->articles_count;
        /** @var int $galleriesCount */
        $galleriesCount = $tag->galleries_count;

        return $eventsCount + $articlesCount + $galleriesCount;
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
        return ! $this->hasChildren($tagId) && ! $this->isInUse($tagId);
    }

    /**
     * @return array<TagResponseDTO>
     */
    public function getByType(string $type): array
    {
        return TagModel::query()
            ->forType($type)
            ->ordered()
            ->get()
            ->map(fn (TagModel $tag) => new TagResponseDTO(
                id: $tag->id,
                name: $tag->name,
                slug: $tag->slug,
                parentId: $tag->parent_id,
                parentName: $tag->parent?->name,
                appliesTo: $tag->applies_to,
                color: $tag->color,
                sortOrder: $tag->sort_order,
            ))
            ->all();
    }

    /**
     * Recursively add a tag and its descendants to the result array using eager-loaded relations.
     *
     * @param  array<TagHierarchyDTO>  $result
     */
    private function addWithDescendantsFromEager(
        TagModel $tag,
        array &$result,
        int $depth,
        string $pathSoFar,
        ?string $type = null,
    ): void {
        // Derive parent name from path for efficiency (avoid loading parent relation)
        $parentName = $depth > 0 ? $this->extractParentNameFromPath($pathSoFar) : null;

        $tagResponseDTO = new TagResponseDTO(
            id: $tag->id,
            name: $tag->name,
            slug: $tag->slug,
            parentId: $tag->parent_id,
            parentName: $parentName,
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

        // Use eager-loaded children (already filtered and ordered)
        foreach ($tag->children as $child) {
            $childPath = $pathSoFar.' > '.$child->name;
            $this->addWithDescendantsFromEager($child, $result, $depth + 1, $childPath, $type);
        }
    }

    /**
     * Extract the parent name from a path like "Root > Parent > Current".
     */
    private function extractParentNameFromPath(string $path): ?string
    {
        $parts = explode(' > ', $path);
        $count = count($parts);

        if ($count < 2) {
            return null;
        }

        // Return the second-to-last part (the parent)
        return $parts[$count - 2];
    }
}
