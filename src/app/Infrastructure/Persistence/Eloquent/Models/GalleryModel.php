<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\GalleryModelFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property-read string|null $cover_image_public_id Derived from first photo by sort_order
 * @property bool $is_published
 * @property bool $is_featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PhotoModel> $photos
 * @property-read Collection<int, TagModel> $tags
 */
final class GalleryModel extends Model
{
    /** @use HasFactory<GalleryModelFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'galleries';

    protected $fillable = [
        'id',
        'title',
        'slug',
        'description',
        'is_published',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    protected static function newFactory(): GalleryModelFactory
    {
        return GalleryModelFactory::new();
    }

    protected static function booted(): void
    {
        // Delete photos manually before gallery deletion to trigger their Cloudinary cleanup
        // (DB cascade won't fire Eloquent events)
        static::deleting(function (GalleryModel $gallery): void {
            $gallery->photos()->each(fn (PhotoModel $photo) => $photo->delete());
        });
    }

    /**
     * @return HasMany<PhotoModel, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PhotoModel::class, 'gallery_id')->orderBy('sort_order');
    }

    /**
     * Get cover image from the first photo by sort_order.
     */
    public function getCoverImagePublicIdAttribute(): ?string
    {
        return $this->photos()->orderBy('sort_order')->first()?->image_public_id;
    }

    /**
     * @return BelongsToMany<TagModel, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TagModel::class, 'gallery_tag', 'gallery_id', 'tag_id')
            ->withTimestamps();
    }
}
