<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\TagModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $parent_id
 * @property array<string> $applies_to
 * @property string $color
 * @property string|null $description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TagModel|null $parent
 * @property-read Collection<int, TagModel> $children
 * @property-read Collection<int, EventModel> $events
 * @property-read Collection<int, ArticleModel> $articles
 * @property-read Collection<int, GalleryModel> $galleries
 *
 * @method static Builder<TagModel> roots()
 * @method static Builder<TagModel> forType(string $type)
 * @method static Builder<TagModel> ordered()
 */
final class TagModel extends Model
{
    /** @use HasFactory<TagModelFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'tags';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'parent_id',
        'applies_to',
        'color',
        'description',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applies_to' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): TagModelFactory
    {
        return TagModelFactory::new();
    }

    /**
     * @return BelongsTo<TagModel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<TagModel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return BelongsToMany<EventModel, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(EventModel::class, 'event_tag', 'tag_id', 'event_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<ArticleModel, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(ArticleModel::class, 'article_tag', 'tag_id', 'article_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<GalleryModel, $this>
     */
    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(GalleryModel::class, 'gallery_tag', 'tag_id', 'gallery_id')
            ->withTimestamps();
    }

    /**
     * Scope to get only root tags (without parent).
     *
     * @param Builder<TagModel> $query
     * @return Builder<TagModel>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to filter tags by type (events, articles, galleries).
     *
     * @param Builder<TagModel> $query
     * @return Builder<TagModel>
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->whereJsonContains('applies_to', $type);
    }

    /**
     * Scope to order tags by sort_order then name.
     *
     * @param Builder<TagModel> $query
     * @return Builder<TagModel>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Check if this tag applies to a specific type.
     */
    public function appliesTo(string $type): bool
    {
        return in_array($type, $this->applies_to, true);
    }

    /**
     * Get the full path of the tag (Parent > Child > Grandchild).
     */
    public function getFullPath(): string
    {
        $path = [$this->name];
        $current = $this;

        while ($current->parent !== null) {
            $current = $current->parent;
            array_unshift($path, $current->name);
        }

        return implode(' > ', $path);
    }

    /**
     * Get the total usage count across all content types.
     */
    public function getUsageCount(): int
    {
        return $this->events()->count()
            + $this->articles()->count()
            + $this->galleries()->count();
    }

    /**
     * Check if the tag has any children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if the tag is in use by any content.
     */
    public function isInUse(): bool
    {
        return $this->getUsageCount() > 0;
    }

    /**
     * Get the nesting depth of the tag.
     */
    public function getDepth(): int
    {
        $depth = 0;
        $current = $this;

        while ($current->parent !== null) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    /**
     * Get the tag name with dashes for table display.
     * Example: "- Child", "-- Grandchild", "--- Great-grandchild"
     */
    public function getIndentedNameForTable(): string
    {
        $depth = $this->getDepth();
        if ($depth === 0) {
            return $this->name;
        }

        $dashes = str_repeat('-', $depth);

        return $dashes . ' ' . $this->name;
    }

    /**
     * Get the tag name with space indentation for select dropdowns.
     * Example: "  Child", "    Grandchild" (2 spaces per depth level)
     */
    public function getIndentedNameForSelect(): string
    {
        $depth = $this->getDepth();
        if ($depth === 0) {
            return $this->name;
        }

        $indent = str_repeat('  ', $depth);

        return $indent . $this->name;
    }

    /**
     * @deprecated Use getIndentedNameForTable() or getIndentedNameForSelect() instead
     */
    public function getIndentedName(): string
    {
        return $this->getIndentedNameForSelect();
    }

    /**
     * Get all tags in hierarchical order as a flat collection.
     *
     * @return Collection<int, TagModel>
     */
    public static function getAllInHierarchicalOrder(): Collection
    {
        $result = new Collection();
        $roots = self::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($roots as $root) {
            self::addWithDescendants($root, $result);
        }

        return $result;
    }

    /**
     * Recursively add a tag and its descendants to the collection.
     *
     * @param Collection<int, TagModel> $collection
     */
    private static function addWithDescendants(TagModel $tag, Collection $collection): void
    {
        $collection->push($tag);

        $children = self::query()
            ->where('parent_id', $tag->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            self::addWithDescendants($child, $collection);
        }
    }

    /**
     * Get hierarchical sort key for database ordering.
     * Returns a sortable string like "00001.00003.00002" based on ancestor sort_orders.
     */
    public function getHierarchySortKey(): string
    {
        $parts = [];
        $current = $this;

        while ($current !== null) {
            array_unshift($parts, str_pad((string) $current->sort_order, 5, '0', STR_PAD_LEFT));
            $current = $current->parent;
        }

        return implode('.', $parts);
    }
}
