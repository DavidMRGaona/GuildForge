<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ModuleModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $display_name
 * @property string $version
 * @property string|null $description
 * @property string|null $author
 * @property string $status
 * @property string|null $path
 * @property string|null $namespace
 * @property string|null $provider
 * @property array<string, string>|null $requires
 * @property array<string>|null $dependencies
 * @property Carbon|null $discovered_at
 * @property Carbon|null $enabled_at
 * @property Carbon|null $installed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ModuleModel extends Model
{
    /** @use HasFactory<ModuleModelFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'modules';

    protected $fillable = [
        'id',
        'name',
        'display_name',
        'version',
        'description',
        'author',
        'status',
        'path',
        'namespace',
        'provider',
        'requires',
        'dependencies',
        'discovered_at',
        'enabled_at',
        'installed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires' => 'array',
            'dependencies' => 'array',
            'discovered_at' => 'datetime',
            'enabled_at' => 'datetime',
            'installed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ModuleModelFactory
    {
        return ModuleModelFactory::new();
    }
}
