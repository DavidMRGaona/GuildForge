<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use Database\Factories\EventModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $location
 * @property string|null $image_public_id
 * @property string|null $member_price
 * @property string|null $non_member_price
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventModel extends Model
{
    /** @use HasFactory<EventModelFactory> */
    use HasFactory;
    use HasUuids;
    use DeletesCloudinaryImages;

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['image_public_id'];

    protected $table = 'events';

    protected $fillable = [
        'id',
        'title',
        'slug',
        'description',
        'start_date',
        'end_date',
        'location',
        'member_price',
        'non_member_price',
        'image_public_id',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'member_price' => 'decimal:2',
            'non_member_price' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    protected static function newFactory(): EventModelFactory
    {
        return EventModelFactory::new();
    }
}
