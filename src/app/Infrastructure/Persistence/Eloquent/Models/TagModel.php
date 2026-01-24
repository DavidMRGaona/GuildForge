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

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

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

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get only root tags (without parent).
     *
     * @param  Builder<TagModel>  $query
     * @return Builder<TagModel>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to filter tags by type (events, articles, galleries).
     *
     * @param  Builder<TagModel>  $query
     * @return Builder<TagModel>
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->whereJsonContains('applies_to', $type);
    }

    /**
     * Scope to order tags by sort_order then name.
     *
     * @param  Builder<TagModel>  $query
     * @return Builder<TagModel>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // =========================================================================
    // SIMPLE ACCESSORS (no queries, use loaded relations)
    // =========================================================================

    /**
     * Check if this tag applies to a specific type.
     */
    public function appliesTo(string $type): bool
    {
        return in_array($type, $this->applies_to, true);
    }

    /**
     * Get the nesting depth of the tag.
     * Note: Uses loaded parent relation, ensure it's eager loaded to avoid N+1.
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
     * Get the tag name with space indentation for select dropdowns.
     * Example: "  Child", "    Grandchild" (2 spaces per depth level)
     */
    public function getIndentedNameForSelect(): string
    {
        $depth = $this->getDepth();
        if ($depth === 0) {
            return $this->name;
        }

        return str_repeat('  ', $depth).$this->name;
    }
}
