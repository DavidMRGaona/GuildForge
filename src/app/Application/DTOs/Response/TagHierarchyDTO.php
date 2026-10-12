<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class TagHierarchyDTO
{
    /**
     * @param  array<string>  $appliesTo
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $parentId,
        public ?string $parentName,
        public array $appliesTo,
        public string $color,
        public int $sortOrder,
        public int $depth,
        public string $fullPath,
        public string $indentedNameForTable,
        public string $indentedNameForSelect,
        public string $hierarchySortKey,
        public ?string $description = null,
    ) {}

    /**
     * Create from TagResponseDTO with computed hierarchical data.
     */
    public static function fromTagResponse(
        TagResponseDTO $tag,
        int $depth,
        string $fullPath,
        ?string $description = null,
    ): self {
        return new self(
            id: $tag->id,
            name: $tag->name,
            slug: $tag->slug,
            parentId: $tag->parentId,
            parentName: $tag->parentName,
            appliesTo: $tag->appliesTo,
            color: $tag->color,
            sortOrder: $tag->sortOrder,
            depth: $depth,
            fullPath: $fullPath,
            indentedNameForTable: self::computeIndentedNameForTable($tag->name, $depth),
            indentedNameForSelect: self::computeIndentedNameForSelect($tag->name, $depth),
            hierarchySortKey: self::computeHierarchySortKey($tag->sortOrder, $depth),
            description: $description,
        );
    }

    private static function computeIndentedNameForTable(string $name, int $depth): string
    {
        if ($depth === 0) {
            return $name;
        }

        return str_repeat('-', $depth).' '.$name;
    }

    private static function computeIndentedNameForSelect(string $name, int $depth): string
    {
        if ($depth === 0) {
            return $name;
        }

        return str_repeat('  ', $depth).$name;
    }

    private static function computeHierarchySortKey(int $sortOrder, int $depth): string
    {
        return str_pad((string) $sortOrder, 5, '0', STR_PAD_LEFT);
    }
}
