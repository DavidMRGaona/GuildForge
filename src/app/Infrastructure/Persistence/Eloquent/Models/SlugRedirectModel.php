<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $old_slug
 * @property string $new_slug
 * @property string $entity_type
 * @property string $entity_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SlugRedirectModel extends Model
{
    use HasUuids;

    protected $table = 'slug_redirects';

    protected $fillable = [
        'id',
        'old_slug',
        'new_slug',
        'entity_type',
        'entity_id',
    ];
}
