<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon $date
 * @property int $emails_sent
 * @property float $estimated_cost
 * @property int $bounces_count
 * @property int $complaints_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SesUsageRecordModel extends Model
{
    use HasUuids;

    protected $table = 'ses_usage_records';

    protected $fillable = [
        'date',
        'emails_sent',
        'estimated_cost',
        'bounces_count',
        'complaints_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'estimated_cost' => 'float',
            'emails_sent' => 'integer',
            'bounces_count' => 'integer',
            'complaints_count' => 'integer',
        ];
    }
}
