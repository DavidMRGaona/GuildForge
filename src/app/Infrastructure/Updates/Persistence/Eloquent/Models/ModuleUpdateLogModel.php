<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $update_history_id
 * @property string $step
 * @property string $status
 * @property string|null $message
 * @property array<string, mixed>|null $context
 * @property \DateTimeImmutable $created_at
 */
final class ModuleUpdateLogModel extends Model
{
    use HasUuids;

    protected $table = 'module_update_logs';

    public $timestamps = false;

    protected $fillable = [
        'update_history_id',
        'step',
        'status',
        'message',
        'context',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ModuleUpdateHistoryModel, $this>
     */
    public function history(): BelongsTo
    {
        return $this->belongsTo(ModuleUpdateHistoryModel::class, 'update_history_id');
    }

    public static function log(
        string $historyId,
        string $step,
        string $status,
        ?string $message = null,
        ?array $context = null
    ): self {
        return self::create([
            'update_history_id' => $historyId,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => $context,
            'created_at' => now(),
        ]);
    }
}
