<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Database\Factories\GalleryModelFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $cover_image_public_id
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PhotoModel> $photos
 */
final class GalleryModel extends Model
{
    /** @use HasFactory<GalleryModelFactory> */
    use HasFactory;
    use HasUuids;
    use DeletesCloudinaryImages;

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['cover_image_public_id'];

    protected $table = 'galleries';

    protected $fillable = [
        'id',
        'title',
        'slug',
        'description',
        'cover_image_public_id',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
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
}
