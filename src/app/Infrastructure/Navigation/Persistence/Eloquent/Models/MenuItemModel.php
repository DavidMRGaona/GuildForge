<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Persistence\Eloquent\Models;

use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property MenuLocation $location
 * @property string|null $parent_id
 * @property string $label
 * @property string|null $url
 * @property string|null $route
 * @property array<string, mixed> $route_params
 * @property string|null $icon
 * @property LinkTarget $target
 * @property MenuVisibility $visibility
 * @property array<string> $permissions
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $module
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MenuItemModel|null $parent
 * @property-read Collection<int, MenuItemModel> $children
 */
final class MenuItemModel extends Model
{
    use HasUuids;

    protected $table = 'menu_items';

    protected $fillable = [
        'id',
        'location',
        'parent_id',
        'label',
        'url',
        'route',
        'route_params',
        'icon',
        'target',
        'visibility',
        'permissions',
        'sort_order',
        'is_active',
        'module',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'target' => LinkTarget::class,
            'visibility' => MenuVisibility::class,
            'route_params' => 'array',
            'permissions' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuItemModel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItemModel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
