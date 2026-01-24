<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Database\Factories\HeroSlideModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $button_text
 * @property string|null $button_url
 * @property string|null $image_public_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|static activeOrdered()
 */
final class HeroSlideModel extends Model
{
    use DeletesCloudinaryImages;

    /** @use HasFactory<HeroSlideModelFactory> */
    use HasFactory;
    use HasUuids;

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['image_public_id'];

    protected $table = 'hero_slides';

    protected $fillable = [
        'id',
        'title',
        'subtitle',
        'button_text',
        'button_url',
        'image_public_id',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Scope to get only active slides ordered by sort_order.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActiveOrdered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    protected static function newFactory(): HeroSlideModelFactory
    {
        return HeroSlideModelFactory::new();
    }
}
