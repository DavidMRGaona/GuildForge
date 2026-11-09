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
 * @property string $subject_type
 * @property string $subject_id
 * @property string $label
 * @property string|null $type_label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class UserMailSubjectMuteModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'user_mail_subject_mutes';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'label',
        'type_label',
    ];
}
