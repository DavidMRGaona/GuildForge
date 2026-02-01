<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Persistence\Eloquent\Models;

use App\Domain\Updates\Enums\UpdateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $module_name
 * @property string $from_version
 * @property string $to_version
 * @property UpdateStatus $status
 * @property string|null $error_message
 * @property string|null $backup_path
 * @property \DateTimeImmutable $started_at
 * @property \DateTimeImmutable|null $completed_at
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class ModuleUpdateHistoryModel extends Model
{
    use HasUuids;

    protected $table = 'module_update_history';

    protected $fillable = [
        'module_name',
        'from_version',
        'to_version',
        'status',
        'error_message',
        'backup_path',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UpdateStatus::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<ModuleUpdateLogModel, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ModuleUpdateLogModel::class, 'update_history_id')
            ->orderBy('created_at');
    }

    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function markCompleted(): void
    {
        $this->status = UpdateStatus::Completed;
        $this->completed_at = now();
        $this->save();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = UpdateStatus::Failed;
        $this->error_message = $errorMessage;
        $this->completed_at = now();
        $this->save();
    }

    public function markRolledBack(): void
    {
        $this->status = UpdateStatus::RolledBack;
        $this->completed_at = now();
        $this->save();
    }

    public function updateStatus(UpdateStatus $status): void
    {
        $this->status = $status;
        $this->save();
    }
}
