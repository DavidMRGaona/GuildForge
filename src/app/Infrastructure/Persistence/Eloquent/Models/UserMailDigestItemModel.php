<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $category
 * @property string $line
 * @property string|null $url
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property Carbon $created_at
 */
final class UserMailDigestItemModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    public const null UPDATED_AT = null;

    protected $table = 'user_mail_digest_items';

    protected $fillable = [
        'user_id',
        'category',
        'line',
        'url',
        'subject_type',
        'subject_id',
    ];
}
