<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $module_name
 * @property string $seeder_class
 * @property \DateTimeImmutable $executed_at
 */
final class ModuleSeederHistoryModel extends Model
{
    use HasUuids;

    protected $table = 'module_seeder_history';

    public $timestamps = false;

    protected $fillable = [
        'module_name',
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

    public static function wasExecuted(string $moduleName, string $seederClass): bool
    {
        return self::query()
            ->where('module_name', $moduleName)
            ->where('seeder_class', $seederClass)
            ->exists();
    }

    public static function markExecuted(string $moduleName, string $seederClass): self
    {
        return self::create([
            'module_name' => $moduleName,
            'seeder_class' => $seederClass,
            'executed_at' => now(),
        ]);
    }

    /**
     * Get all executed seeders for a module.
     *
     * @return array<string>
     */
    public static function getExecutedSeeders(string $moduleName): array
    {
        return self::query()
            ->where('module_name', $moduleName)
            ->pluck('seeder_class')
            ->toArray();
    }
}
