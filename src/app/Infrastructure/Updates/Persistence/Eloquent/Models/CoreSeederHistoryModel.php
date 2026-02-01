<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $seeder_class
 * @property \DateTimeImmutable $executed_at
 */
final class CoreSeederHistoryModel extends Model
{
    use HasUuids;

    protected $table = 'core_seeder_history';

    public $timestamps = false;

    protected $fillable = [
        'seeder_class',
        'executed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_at' => 'immutable_datetime',
        ];
    }

    public static function wasExecuted(string $seederClass): bool
    {
        return self::query()
            ->where('seeder_class', $seederClass)
            ->exists();
    }

    public static function markExecuted(string $seederClass): self
    {
        return self::create([
            'seeder_class' => $seederClass,
            'executed_at' => now(),
        ]);
    }

    /**
     * Get all executed seeders.
     *
     * @return array<string>
     */
    public static function getExecutedSeeders(): array
    {
        return self::query()
            ->pluck('seeder_class')
            ->toArray();
    }
}
