<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One module request of an event proposal (core#581). Written only through
 * EventProposalRequestServiceInterface.
 *
 * @property string $id
 * @property string $event_id
 * @property string $request_key
 * @property array<string, bool|int|string|null> $requested_values
 * @property array<string, bool|int|string|null>|null $approved_values
 * @property bool $apply
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventProposalRequestModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'event_proposal_requests';

    protected $fillable = [
        'event_id',
        'request_key',
        'requested_values',
        'approved_values',
        'apply',
        'applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_values' => 'array',
            'approved_values' => 'array',
            'apply' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }
}
