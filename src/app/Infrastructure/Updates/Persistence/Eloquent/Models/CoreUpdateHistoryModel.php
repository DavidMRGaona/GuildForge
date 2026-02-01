<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Persistence\Eloquent\Models;

use App\Domain\Updates\Enums\UpdateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $from_version
 * @property string $to_version
 * @property string $git_commit_before
 * @property string|null $git_commit_after
 * @property UpdateStatus $status
 * @property string|null $error_message
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class CoreUpdateHistoryModel extends Model
{
    use HasUuids;

    protected $table = 'core_update_history';

    protected $fillable = [
        'from_version',
        'to_version',
        'git_commit_before',
        'git_commit_after',
        'status',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UpdateStatus::class,
        ];
    }

    public function markCompleted(string $commitAfter): void
    {
        $this->status = UpdateStatus::Completed;
        $this->git_commit_after = $commitAfter;
        $this->save();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = UpdateStatus::Failed;
        $this->error_message = $errorMessage;
        $this->save();
    }
}
