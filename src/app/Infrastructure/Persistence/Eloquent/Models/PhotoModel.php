<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Database\Factories\PhotoModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $gallery_id
 * @property string $image_public_id
 * @property string|null $caption
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GalleryModel $gallery
 */
final class PhotoModel extends Model
{
    /** @use HasFactory<PhotoModelFactory> */
    use HasFactory;
    use HasUuids;
    use DeletesCloudinaryImages;

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['image_public_id'];

    protected $table = 'photos';

    protected $fillable = [
        'id',
        'gallery_id',
        'image_public_id',
        'caption',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function newFactory(): PhotoModelFactory
    {
        return PhotoModelFactory::new();
    }

    /**
     * @return BelongsTo<GalleryModel, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(GalleryModel::class, 'gallery_id');
    }
}
